"""Automatic checks over results/*.json produced by run.php.
- tool:   was the expected tool called with the expected parameters
- pct:    is a risk index ever shown as a percentage / probability
- nums:   numbers in the reply that do not appear anywhere in the tool results
          (candidates for hallucination; reviewed manually afterwards)
Usage (from project root): python tools/llm-eval/check.py tools/llm-eval
"""
import json, glob, os, re, sys

sys.stdout.reconfigure(encoding="utf-8")
D = sys.argv[1]
cases = {c["id"]: c for c in json.load(open(os.path.join(D, "cases.json"), encoding="utf-8"))}


def calls(r, name):
    return [c["input"] for c in r["tool_calls"] if c["name"] == name]


def tool_ok(r):
    i = r["id"]
    zr = calls(r, "get_zone_risk")
    if i == "T01": return any(c.get("block") in (4, 5) or "block" not in c for c in zr)
    if i == "T02": return any(c.get("dow") == 5 and c.get("block") in (1, 2) for c in zr)
    if i == "T03": return bool(calls(r, "get_zones"))
    if i == "T04": return bool(calls(r, "get_model_quality"))
    if i == "T05": return bool(calls(r, "get_insights"))
    if i == "T06": return any(c.get("dow") == 6 and c.get("block") == 0 for c in zr)
    if i in ("T07", "T11"): return bool(zr)
    if i == "T08": return bool(calls(r, "get_zones") or zr)
    if i == "T09": return bool(zr)
    if i == "T10": return not r["tool_calls"]
    if i == "T12":
        return any(c.get("block") == 3 for c in zr) and any(c.get("block") in (4, 5) for c in zr) and len(zr) >= 2
    if i == "T13": return True  # no tool provides this — judged on the reply only
    return None


NUM = re.compile(r"(?<![\w.])(\d+(?:[.,]\d+)?)(?![\w])")


def numbers_not_in_results(r):
    blob = " ".join(r["tool_results"])
    blob_nums = set()
    for n in NUM.findall(blob):
        try:
            blob_nums.add(round(float(n.replace(",", ".")), 3))
        except ValueError:
            pass
    missing = []
    for n in NUM.findall(r["reply"] or ""):
        v = round(float(n.replace(",", ".")), 3)
        # tolerate rounding of shares shown as percentages (0.532 -> 53 %)
        if v in blob_nums or any(abs(v - b) < 0.6 or abs(v - b * 100) < 0.6 for b in blob_nums):
            continue
        missing.append(n)
    return missing


rows = []
for f in sorted(glob.glob(os.path.join(D, "results", "*.json"))):
    r = json.load(open(f, encoding="utf-8"))
    reply = r["reply"] or ""
    pct = bool(re.search(r"(indeks\w*|rizik\w*)[^.\n]{0,40}\d+[.,]?\d*\s?%|vjerojatnost\w*[^.\n]{0,30}\d+[.,]?\d*\s?%", reply, re.I))
    rows.append((r["id"], r["rep"], r["seconds"], r["status"], tool_ok(r), pct, numbers_not_in_results(r),
                 [c["name"] + json.dumps(c["input"]) for c in r["tool_calls"]]))

for row in rows:
    print(f"{row[0]} r{row[1]} {row[2]:>6}s st={row[3]} tool_ok={row[4]} pct={row[5]} unexplained_nums={row[6]} calls={row[7]}")
