"""
DBSCAN-hotspot ("zone") pipeline: stable K1..KN zone labeling, zone-vs-flight
comparison, a real (zone, day, time-block) supervised dataset built from
actual historical outcomes (no synthetic background sampling), a 5-model
comparison with a chronological train/test split, and probability
predictions from the best-performing model.

Kept separate from main.py (which already covers the risk-grid/bandit/KDE
pipelines) purely for file size / readability.
"""
import numpy as np
import pandas as pd
from sklearn.cluster import DBSCAN
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.neighbors import KNeighborsClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.preprocessing import StandardScaler
from sklearn.utils.class_weight import compute_sample_weight

from ml_common import evaluate_model

# Mirrors config('surveillance.time_blocks') in the Laravel app (config/surveillance.php).
# Keep these two definitions in sync by hand — there is no shared config store
# between the PHP and Python runtimes.
TIME_BLOCKS = [
    {"label": "00:00–04:00", "start": 0, "end": 4},
    {"label": "04:00–08:00", "start": 4, "end": 8},
    {"label": "08:00–12:00", "start": 8, "end": 12},
    {"label": "12:00–16:00", "start": 12, "end": 16},
    {"label": "16:00–20:00", "start": 16, "end": 20},
    {"label": "20:00–24:00", "start": 20, "end": 24},
]
MIN_FLIGHTS_FOR_ZONE = 5  # mirrors config('surveillance.min_flights_for_zone')

DOW_LABELS_HR = {0: "ponedjeljkom", 1: "utorkom", 2: "srijedom", 3: "četvrtkom",
                 4: "petkom", 5: "subotom", 6: "nedjeljom"}  # Python weekday(): 0=Mon..6=Sun


def _block_for_hour(hour: int) -> int:
    for i, b in enumerate(TIME_BLOCKS):
        if b["start"] <= hour < b["end"]:
            return i
    return len(TIME_BLOCKS) - 1


# ── Phase 1: stable DBSCAN zones ───────────────────────────────────────────────
def compute_zones(df: pd.DataFrame, eps_km: float = 2.0, min_samples: int = 3) -> dict:
    """DBSCAN once over all current detections (df must have latitude/
    longitude/type/count/hour/dow columns, i.e. main.get_detections()'s
    output). Zones are labeled K1..KN by descending detection count — a
    stable-enough identity for this dataset's scale, not a persisted zone
    registry (a real production system would want the latter).

    Also returns `point_zone_ids`: the exact zone id (or None for noise)
    DBSCAN assigned to each row of `df`, in df's original row order. This
    is the ONLY correct way to know which zone a detection belongs to —
    downstream code must reuse it rather than re-deriving membership from
    a circle-radius check against the zone centroid, which (being a
    convex approximation of what can be a non-convex DBSCAN cluster) can
    disagree with the real clustering by a point or two."""
    if len(df) < min_samples:
        return {"zones": [], "noise": [], "total_detections": len(df), "point_zone_ids": [None] * len(df)}

    coords_rad = np.radians(df[["latitude", "longitude"]].values.astype(float))
    eps_rad = eps_km / 6371.0
    labels = DBSCAN(eps=eps_rad, min_samples=min_samples, metric="haversine").fit_predict(coords_rad)
    df = df.copy()
    df["cluster"] = labels

    raw = []
    for cid in sorted(set(labels)):
        if cid == -1:
            continue
        grp = df[df.cluster == cid]
        total_count = int(grp["count"].sum())
        avg_entities = float(grp["count"].mean())
        dominant_type = grp["type"].mode().iloc[0] if not grp.empty else "other"
        risk = min(100, int(len(grp) * 10 + avg_entities * 8 + total_count * 0.5))
        lat, lon = float(grp["latitude"].mean()), float(grp["longitude"].mean())

        dow_mode = int(grp["dow"].mode().iloc[0]) if grp["dow"].notna().any() else None
        blocks = grp["hour"].dropna().astype(int).map(_block_for_hour)
        most_active_block = int(blocks.mode().iloc[0]) if not blocks.empty else None

        raw.append({
            "_raw_cid": cid,
            "count": len(grp), "lat": lat, "lon": lon,
            "total_individuals": total_count, "avg_entities": round(avg_entities, 2),
            "dominant_type": dominant_type, "risk": risk,
            "radius_km": float(grp.apply(
                lambda r: np.sqrt((r.latitude - lat) ** 2 + (r.longitude - lon) ** 2) * 111, axis=1
            ).max()) if len(grp) > 1 else 0.5,
            "most_active_dow": dow_mode,
            "most_active_block": most_active_block,
        })

    raw.sort(key=lambda z: z["count"], reverse=True)
    zones = []
    raw_cid_to_kid = {}
    for i, z in enumerate(raw):
        z["id"] = f"K{i + 1}"
        raw_cid_to_kid[z.pop("_raw_cid")] = z["id"]
        zones.append(z)

    point_zone_ids = [raw_cid_to_kid.get(c) for c in labels]  # None stays None (noise, cid -1)

    noise_pts = df[df.cluster == -1][["latitude", "longitude"]].values.tolist()
    return {"zones": zones, "noise": noise_pts, "total_detections": len(df), "point_zone_ids": point_zone_ids}


def _flight_zone_membership(zones: list, flight_points: pd.DataFrame) -> pd.DataFrame:
    """(zone, flight_id) pairs — a flight belongs to a zone if ANY of its
    GPX points falls within that zone's radius (whole-flight attribution,
    the same simplification RecommendationService already uses for
    station-level zones rather than pro-rating partial time-in-zone)."""
    rows = []
    if flight_points.empty:
        return pd.DataFrame(rows, columns=["zone", "flight_id"])
    lat = flight_points["latitude"].astype(float).to_numpy()
    lon = flight_points["longitude"].astype(float).to_numpy()
    fids = flight_points["flight_id"].to_numpy()
    for z in zones:
        d = np.sqrt((lat - z["lat"]) ** 2 + (lon - z["lon"]) ** 2) * 111.0
        for fid in np.unique(fids[d <= z["radius_km"]]):
            rows.append((z["id"], fid))
    return pd.DataFrame(rows, columns=["zone", "flight_id"]).drop_duplicates()


# ── Phase 2: zone vs. GPX flights ──────────────────────────────────────────────
def zone_flight_stats(zones: list, detections_df: pd.DataFrame, flights_meta: pd.DataFrame,
                       flight_points: pd.DataFrame, recent_days: int = 30) -> list:
    """Per zone: flights through it, surveillance hours, detections found,
    the key detections-per-hour rate, activity timing, and a 30d/prev-30d
    trend — the same shape of metric RecommendationService already computes
    per station, just keyed by DBSCAN zone instead.

    `detections_df` must already carry the exact per-row "zone" column that
    `compute_zones()`'s `point_zone_ids` produced (see `_zone_pipeline_inputs`
    in main.py) — detections have no other correct zone assignment, since
    DBSCAN was run directly on them."""
    if not zones:
        return []

    det = detections_df.copy()

    flight_zone = _flight_zone_membership(zones, flight_points)
    fm = flights_meta.copy()
    fm["flight_date"] = pd.to_datetime(fm["flight_date"])
    fzm = flight_zone.merge(fm, left_on="flight_id", right_on="id", how="left")

    now = pd.Timestamp.now()
    results = []
    for z in zones:
        zid = z["id"]
        zdet = det[det["zone"] == zid].copy()
        zdet["detected_at"] = pd.to_datetime(zdet["detected_at"])
        zfz = fzm[fzm["zone"] == zid]

        flights_count = int(zfz["flight_id"].nunique())
        total_minutes = float(zfz["duration_minutes"].fillna(0).sum())
        total_hours = round(total_minutes / 60, 1)

        flights_with_det = int(zdet["flight_id"].dropna().nunique())
        flights_without_det = max(0, flights_count - flights_with_det)

        detections_count = len(zdet)
        entities_count = int(zdet["count"].sum())
        rate = round(detections_count / total_hours, 3) if total_hours > 0 else 0.0

        recent = zdet[zdet["detected_at"] >= now - pd.Timedelta(days=recent_days)]
        previous = zdet[(zdet["detected_at"] < now - pd.Timedelta(days=recent_days)) &
                         (zdet["detected_at"] >= now - pd.Timedelta(days=2 * recent_days))]
        recent_count, previous_count = len(recent), len(previous)
        trend = round((recent_count - previous_count) / previous_count * 100, 1) if previous_count > 0 \
            else (100.0 if recent_count > 0 else 0.0)

        last_detection = zdet["detected_at"].max() if not zdet.empty else None

        results.append({
            "id": zid, "lat": z["lat"], "lon": z["lon"], "radius_km": z["radius_km"],
            "flights_count": flights_count, "total_hours": total_hours,
            "flights_with_detection": flights_with_det, "flights_without_detection": flights_without_det,
            "detections_count": detections_count, "entities_count": entities_count,
            "rate_per_hour": rate,
            "most_active_dow": z.get("most_active_dow"), "most_active_block": z.get("most_active_block"),
            "last_detection_at": last_detection.strftime("%Y-%m-%d %H:%M") if last_detection is not None and not pd.isna(last_detection) else None,
            "recent_count": recent_count, "previous_count": previous_count, "trend_pct": trend,
            "sufficient_data": flights_count >= MIN_FLIGHTS_FOR_ZONE,
        })

    return results


# ── Phase 3: supervised (zone, day, block) dataset ─────────────────────────────
def build_zone_timeline_dataset(zones: list, detections_df: pd.DataFrame,
                                 flights_meta: pd.DataFrame, flight_points: pd.DataFrame) -> pd.DataFrame:
    """One row per (zone, day, time-block) across the full data history.
    Every feature is a cumulative/rolling stat *shifted back one day* so
    nothing about the outcome being predicted leaks into its own features.
    Label = a real detection actually happened in that zone during that
    exact day+block (a genuine negative example otherwise — never a
    synthetic random background point).

    `detections_df` must already carry the exact per-row "zone" column
    from `compute_zones()`'s `point_zone_ids` (see `_zone_pipeline_inputs`
    in main.py) — same reasoning as `zone_flight_stats()` above."""
    if not zones:
        return pd.DataFrame()

    det = detections_df.copy()
    det = det.dropna(subset=["zone"])
    det["detected_at"] = pd.to_datetime(det["detected_at"])
    det["date"] = det["detected_at"].dt.normalize()
    det["block"] = det["hour"].astype(int).map(_block_for_hour)

    flight_zone = _flight_zone_membership(zones, flight_points)
    fm = flights_meta.copy()
    fm["flight_date"] = pd.to_datetime(fm["flight_date"])
    fm["date"] = fm["flight_date"].dt.normalize()
    fzm = flight_zone.merge(fm, left_on="flight_id", right_on="id", how="left")

    if det.empty and fzm.empty:
        return pd.DataFrame()

    min_date = min([d for d in [det["date"].min() if not det.empty else None,
                                 fzm["date"].min() if not fzm.empty else None] if d is not None])
    max_date = max([d for d in [det["date"].max() if not det.empty else None,
                                 fzm["date"].max() if not fzm.empty else None] if d is not None])
    day_range = pd.date_range(min_date, max_date, freq="D")
    if len(day_range) <= 30:
        return pd.DataFrame()

    rows = []
    for z in zones:
        zid = z["id"]
        zdet = det[det["zone"] == zid]
        zfz = fzm[fzm["zone"] == zid]

        daily_det = zdet.groupby("date").size().reindex(day_range, fill_value=0)
        daily_flights = zfz.groupby("date")["flight_id"].nunique().reindex(day_range, fill_value=0)
        daily_minutes = zfz.groupby("date")["duration_minutes"].sum().reindex(day_range, fill_value=0)

        block_pivot = zdet.groupby(["date", "block"]).size().unstack(fill_value=0)
        block_pivot = block_pivot.reindex(day_range, fill_value=0)
        for b in range(len(TIME_BLOCKS)):
            if b not in block_pivot.columns:
                block_pivot[b] = 0
        block_pivot = block_pivot[sorted(block_pivot.columns)]

        no_det_day = (daily_det == 0).astype(int)
        daily_flights_no_det = daily_flights * no_det_day

        flights_cum = daily_flights.cumsum().shift(1).fillna(0)
        minutes_cum = daily_minutes.cumsum().shift(1).fillna(0)
        dets_cum = daily_det.cumsum().shift(1).fillna(0)
        flights_no_det_cum = daily_flights_no_det.cumsum().shift(1).fillna(0)

        dets_7d = daily_det.rolling(7, min_periods=1).sum().shift(1).fillna(0)
        dets_30d = daily_det.rolling(30, min_periods=1).sum().shift(1).fillna(0)
        flights_30d = daily_flights.rolling(30, min_periods=1).sum().shift(1).fillna(0)
        minutes_30d = daily_minutes.rolling(30, min_periods=1).sum().shift(1).fillna(0)
        prev_30d = dets_30d.shift(30).fillna(0)

        had_det_yesterday = daily_det.shift(1).fillna(0) > 0
        last_det_day = pd.Series(np.where(had_det_yesterday, day_range, pd.NaT), index=day_range)
        last_det_day = last_det_day.ffill()
        days_since = (day_range.values - pd.to_datetime(last_det_day).values) / np.timedelta64(1, "D")
        days_since = pd.Series(days_since, index=day_range).fillna(9999).clip(upper=9999)

        with np.errstate(divide="ignore", invalid="ignore"):
            trend = np.where(prev_30d.values > 0, (dets_30d.values - prev_30d.values) / prev_30d.values * 100,
                              np.where(dets_30d.values > 0, 100.0, 0.0))

        for i, day in enumerate(day_range):
            if i < 30:
                continue  # need real history before the rolling features mean anything
            for b in range(len(TIME_BLOCKS)):
                rows.append({
                    "zone": zid,
                    "date": day.strftime("%Y-%m-%d"),
                    "block": b,
                    "dow": int(day.dayofweek),
                    "month": int(day.month),
                    "hour": TIME_BLOCKS[b]["start"],
                    "flights_cum": float(flights_cum.iloc[i]),
                    "minutes_cum": float(minutes_cum.iloc[i]),
                    "dets_cum": float(dets_cum.iloc[i]),
                    "flights_30d": float(flights_30d.iloc[i]),
                    "minutes_30d": float(minutes_30d.iloc[i]),
                    "dets_7d": float(dets_7d.iloc[i]),
                    "dets_30d": float(dets_30d.iloc[i]),
                    "flights_no_det_cum": float(flights_no_det_cum.iloc[i]),
                    "days_since_last_det": float(days_since.iloc[i]),
                    "trend": float(trend[i]),
                    "label": int(block_pivot.iloc[i, b] > 0),
                })

    return pd.DataFrame(rows)


NUMERIC_FEATURES = ["flights_cum", "minutes_cum", "dets_cum", "flights_30d", "minutes_30d",
                     "dets_7d", "dets_30d", "flights_no_det_cum", "days_since_last_det", "trend"]


def _feature_matrix(dataset: pd.DataFrame):
    hour_rad = dataset["hour"].astype(float) / 24 * 2 * np.pi
    dow_rad = dataset["dow"].astype(float) / 7 * 2 * np.pi
    month_rad = dataset["month"].astype(float) / 12 * 2 * np.pi
    X = pd.DataFrame({
        "hour_sin": np.sin(hour_rad), "hour_cos": np.cos(hour_rad),
        "dow_sin": np.sin(dow_rad), "dow_cos": np.cos(dow_rad),
        "month_sin": np.sin(month_rad), "month_cos": np.cos(month_rad),
    }, index=dataset.index)
    for col in NUMERIC_FEATURES:
        X[col] = dataset[col].astype(float)
    zone_dummies = pd.get_dummies(dataset["zone"], prefix="zone")
    X = pd.concat([X, zone_dummies], axis=1)
    y = dataset["label"].astype(int) if "label" in dataset else None
    return X, y


MODEL_SPECS = [
    ("logistic_regression", "Logistic Regression", True, lambda: LogisticRegression(max_iter=1000)),
    ("knn", "k-Nearest Neighbors", True, lambda: KNeighborsClassifier(n_neighbors=15)),
    ("random_forest", "Random Forest", False, lambda: RandomForestClassifier(
        n_estimators=200, max_depth=8, min_samples_leaf=4, random_state=42, n_jobs=-1)),
    ("gradient_boosting", "Gradient Boosting", False, lambda: GradientBoostingClassifier(
        n_estimators=150, max_depth=3, random_state=42)),
    ("mlp", "MLP (neuronska mreža)", True, lambda: MLPClassifier(
        hidden_layer_sizes=(32, 16), max_iter=500, random_state=42)),
]


# ── Phase 4: 5-model comparison, chronological split ───────────────────────────
def compare_zone_models(dataset: pd.DataFrame) -> dict:
    if dataset.empty or dataset["label"].sum() < 5:
        return {"trained": False, "message": "Nedovoljno pozitivnih primjera za pouzdanu procjenu modela."}

    dataset = dataset.sort_values("date").reset_index(drop=True)
    cutoff = int(len(dataset) * 0.8)
    X, y = _feature_matrix(dataset)
    X_train, X_test = X.iloc[:cutoff], X.iloc[cutoff:]
    y_train, y_test = y.iloc[:cutoff], y.iloc[cutoff:]

    if y_train.nunique() < 2 or y_test.nunique() < 2:
        return {"trained": False, "message": "Kronološki test skup nema oba razreda — premalo podataka za pouzdanu procjenu."}

    w_train = compute_sample_weight("balanced", y_train)
    scaler = StandardScaler().fit(X_train)
    X_train_scaled = pd.DataFrame(scaler.transform(X_train), columns=X_train.columns, index=X_train.index)
    X_test_scaled = pd.DataFrame(scaler.transform(X_test), columns=X_test.columns, index=X_test.index)

    results = {}
    for key, label, needs_scaling, make_model in MODEL_SPECS:
        xtr = X_train_scaled if needs_scaling else X_train
        xte = X_test_scaled if needs_scaling else X_test
        results[key] = {"label": label, **evaluate_model(make_model(), xtr, y_train, w_train, xte, y_test,
                                                           feature_names=list(X.columns))}

    best_key = max(results, key=lambda k: (results[k]["f1"], results[k]["roc_auc"] or 0))

    return {
        "trained": True,
        "n_rows": len(dataset), "n_positive": int(y.sum()),
        "train_size": len(X_train), "test_size": len(X_test),
        "split_date": dataset.iloc[cutoff]["date"] if cutoff < len(dataset) else dataset.iloc[-1]["date"],
        "models": results,
        "best_model": best_key,
    }


# Training all 5 models is expensive (~tens of seconds); a single academic
# page load hits both /zone-model-metrics and /zone-predictions (the latter
# needs "best_model" whenever no explicit model is requested), so an
# in-process cache keyed by dataset shape avoids paying for it twice per load.
_model_comparison_cache: dict = {"key": None, "result": None}


def compare_zone_models_cached(dataset: pd.DataFrame) -> dict:
    key = (len(dataset), dataset["date"].max() if not dataset.empty else None, int(dataset["label"].sum()) if not dataset.empty else 0)
    if _model_comparison_cache["key"] == key:
        return _model_comparison_cache["result"]
    result = compare_zone_models(dataset)
    _model_comparison_cache["key"] = key
    _model_comparison_cache["result"] = result
    return result


# ── Phase 5: predictive probabilities per zone ─────────────────────────────────
def predict_zone_probabilities(dataset: pd.DataFrame, zones: list, flight_stats: list,
                                model_key: str = "gradient_boosting", dow: int | None = None,
                                block: int | None = None) -> list:
    if dataset.empty:
        return []

    X, y = _feature_matrix(dataset)
    spec = next((s for s in MODEL_SPECS if s[0] == model_key), None) or next(s for s in MODEL_SPECS if s[0] == "gradient_boosting")
    _, _, needs_scaling, make_model = spec
    model = make_model()
    w = compute_sample_weight("balanced", y)

    scaler = None
    X_fit = X
    if needs_scaling:
        scaler = StandardScaler().fit(X)
        X_fit = pd.DataFrame(scaler.transform(X), columns=X.columns)

    try:
        model.fit(X_fit, y, sample_weight=w)
    except TypeError:
        model.fit(X_fit, y)

    now = pd.Timestamp.now()
    target_dow = dow if dow is not None else int(now.dayofweek)
    target_block = block if block is not None else _block_for_hour(now.hour)
    target_hour = TIME_BLOCKS[target_block]["start"]
    target_month = int(now.month)

    latest = dataset.sort_values("date").groupby("zone").tail(1).set_index("zone")
    stats_by_id = {s["id"]: s for s in flight_stats}

    results = []
    for z in zones:
        zid = z["id"]
        stats = stats_by_id.get(zid, {})
        base = {
            "id": zid, "lat": z["lat"], "lon": z["lon"], "radius_km": z["radius_km"],
            "flights_count": stats.get("flights_count", 0), "total_hours": stats.get("total_hours", 0),
            "detections_count": stats.get("detections_count", 0), "rate_per_hour": stats.get("rate_per_hour", 0),
            "most_active_dow": stats.get("most_active_dow"), "most_active_block": stats.get("most_active_block"),
        }

        if zid not in latest.index or not stats.get("sufficient_data", False):
            results.append({**base, "probability": None, "tier": "insufficient_data"})
            continue

        row = latest.loc[zid].copy()
        row["zone"] = zid  # set_index("zone") above dropped it from the columns
        row["hour"] = target_hour
        row["dow"] = target_dow
        row["month"] = target_month
        row["block"] = target_block
        row_df = pd.DataFrame([row])
        Xq, _ = _feature_matrix(row_df)
        Xq = Xq.reindex(columns=X.columns, fill_value=0)
        if scaler is not None:
            Xq = pd.DataFrame(scaler.transform(Xq), columns=Xq.columns)

        proba = round(float(model.predict_proba(Xq)[0, 1]) * 100, 1)
        tier = "red" if proba >= 70 else "orange" if proba >= 50 else "yellow" if proba >= 25 else "green"

        results.append({**base, "probability": proba, "tier": tier})

    return results
