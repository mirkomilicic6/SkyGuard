from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import pymysql
import pandas as pd
import numpy as np
from sklearn.cluster import DBSCAN
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import RandomForestClassifier
from dotenv import load_dotenv
import os

load_dotenv()

app = FastAPI(title="DroneManager ML Service", version="1.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

DB = dict(
    host=os.getenv("DB_HOST", "127.0.0.1"),
    port=int(os.getenv("DB_PORT", 3306)),
    user=os.getenv("DB_USERNAME", "root"),
    password=os.getenv("DB_PASSWORD", ""),
    database=os.getenv("DB_DATABASE", "drone_manager"),
    charset="utf8mb4",
)


def get_detections(station_id: int | None = None, administration_id: int | None = None) -> pd.DataFrame:
    conn = pymysql.connect(**DB)
    sql = """
        SELECT
            d.id, d.latitude, d.longitude, d.type, d.count,
            d.detected_at, d.escalation_level, d.confirmed, d.source,
            HOUR(d.detected_at)       AS hour,
            DAYOFWEEK(d.detected_at)  AS dow,
            COALESCE(f.station_id, hc.station_id) AS station_id
        FROM detections d
        LEFT JOIN flights f ON f.id = d.flight_id
        LEFT JOIN hunting_cameras hc ON hc.id = d.camera_id
        LEFT JOIN border_police_stations bps
            ON bps.id = COALESCE(f.station_id, hc.station_id)
        WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL
    """
    params = []
    if administration_id:
        sql += " AND bps.police_administration_id = %s"
        params = [administration_id]
    elif station_id:
        sql += " AND (f.station_id = %s OR hc.station_id = %s)"
        params = [station_id, station_id]

    df = pd.read_sql(sql, conn, params=params or None)
    conn.close()
    return df


def get_flight_points(station_id: int | None = None, administration_id: int | None = None) -> pd.DataFrame:
    conn = pymysql.connect(**DB)
    sql = """
        SELECT g.latitude, g.longitude
        FROM gpx_points g
        JOIN flights f ON f.id = g.flight_id
        LEFT JOIN border_police_stations bps ON bps.id = f.station_id
        WHERE g.latitude IS NOT NULL AND g.longitude IS NOT NULL
    """
    params = []
    if administration_id:
        sql += " AND bps.police_administration_id = %s"
        params = [administration_id]
    elif station_id:
        sql += " AND f.station_id = %s"
        params = [station_id]

    df = pd.read_sql(sql, conn, params=params or None)
    conn.close()
    return df


def get_station_points() -> np.ndarray:
    """All border police station coordinates — used as known Croatian-side
    reference points when figuring out which way is 'into Croatia' at a
    given spot on the border."""
    conn = pymysql.connect(**DB)
    df = pd.read_sql(
        "SELECT latitude, longitude FROM border_police_stations "
        "WHERE latitude IS NOT NULL AND longitude IS NOT NULL",
        conn,
    )
    conn.close()
    if df.empty:
        return np.empty((0, 2))
    return df[["latitude", "longitude"]].astype(float).to_numpy()


# ── Health ────────────────────────────────────────────────────────────────────
@app.get("/health")
def health():
    return {"status": "ok"}


# ── DBSCAN clusters ───────────────────────────────────────────────────────────
@app.get("/clusters")
def clusters(station_id: int = None, administration_id: int = None, eps_km: float = 2.0, min_samples: int = 3):
    df = get_detections(station_id, administration_id)
    if len(df) < min_samples:
        return {"clusters": [], "noise": []}

    coords = df[["latitude", "longitude"]].values.astype(float)
    # DBSCAN with haversine distance (radians input)
    coords_rad = np.radians(coords)
    eps_rad = eps_km / 6371.0  # Earth radius km

    labels = DBSCAN(eps=eps_rad, min_samples=min_samples, metric="haversine").fit_predict(coords_rad)
    df["cluster"] = labels

    result = []
    for cid in sorted(set(labels)):
        if cid == -1:
            continue
        grp = df[df.cluster == cid]
        total_count = int(grp["count"].sum())
        avg_esc = float(grp["escalation_level"].mean())
        dominant_type = grp["type"].mode().iloc[0] if not grp.empty else "other"

        # Risk score 0–100
        risk = min(100, int(
            (len(grp) * 10) +
            (avg_esc * 15) +
            (total_count * 0.5)
        ))

        result.append({
            "id": int(cid),
            "lat": float(grp["latitude"].mean()),
            "lon": float(grp["longitude"].mean()),
            "count": len(grp),
            "total_individuals": total_count,
            "avg_escalation": round(avg_esc, 2),
            "dominant_type": dominant_type,
            "risk": risk,
            "radius_km": float(grp.apply(
                lambda r: np.sqrt((r.latitude - grp.latitude.mean())**2 + (r.longitude - grp.longitude.mean())**2) * 111,
                axis=1
            ).max()) if len(grp) > 1 else 0.5,
        })

    result.sort(key=lambda x: x["risk"], reverse=True)
    noise_pts = df[df.cluster == -1][["latitude", "longitude"]].values.tolist()

    return {"clusters": result, "noise": noise_pts, "total_detections": len(df)}


# ── Risk matrix (hour × weekday) ──────────────────────────────────────────────
@app.get("/risk-matrix")
def risk_matrix(station_id: int = None, administration_id: int = None):
    df = get_detections(station_id, administration_id)
    if df.empty:
        return {"matrix": [], "peak_hour": None, "peak_day": None}

    # Build 24×7 matrix (hour × dow, dow: 1=Sun..7=Sat → remap to 0=Mon..6=Sun)
    matrix = np.zeros((24, 7))
    dow_map = {2: 0, 3: 1, 4: 2, 5: 3, 6: 4, 7: 5, 1: 6}

    for _, row in df.iterrows():
        h = int(row["hour"])
        d = dow_map.get(int(row["dow"]), 0)
        weight = 1 + int(row["escalation_level"]) * 0.5
        matrix[h][d] += weight

    # Normalise to 0–100
    mx = matrix.max()
    if mx > 0:
        matrix = (matrix / mx * 100).round(1)

    peak_hour = int(matrix.sum(axis=1).argmax())
    peak_day_idx = int(matrix.sum(axis=0).argmax())
    days = ["Ponedjeljak", "Utorak", "Srijeda", "Četvrtak", "Petak", "Subota", "Nedjelja"]

    return {
        "matrix": matrix.tolist(),
        "peak_hour": peak_hour,
        "peak_day": days[peak_day_idx],
        "peak_day_idx": peak_day_idx,
    }


# ── Type breakdown + trend ────────────────────────────────────────────────────
@app.get("/insights")
def insights(station_id: int = None, administration_id: int = None):
    df = get_detections(station_id, administration_id)
    if df.empty:
        return {}

    by_type = df.groupby("type")["count"].sum().to_dict()
    by_source = df["source"].value_counts().to_dict()

    # Last 30 days trend
    df["detected_at"] = pd.to_datetime(df["detected_at"])
    recent = df[df["detected_at"] >= pd.Timestamp.now() - pd.Timedelta(days=30)]
    daily = recent.groupby(recent["detected_at"].dt.date).size()

    # Simple linear trend slope
    if len(daily) >= 2:
        x = np.arange(len(daily))
        slope = float(np.polyfit(x, daily.values, 1)[0])
        trend = "rast" if slope > 0.05 else ("pad" if slope < -0.05 else "stabilan")
    else:
        slope, trend = 0.0, "stabilan"

    confirmed_pct = round(df["confirmed"].sum() / len(df) * 100, 1) if len(df) else 0

    return {
        "total": len(df),
        "by_type": by_type,
        "by_source": by_source,
        "confirmed_pct": confirmed_pct,
        "trend_30d": trend,
        "trend_slope": round(slope, 3),
        "high_risk_count": int((df["escalation_level"] >= 2).sum()),
    }


# ── Predict risk for location + time ─────────────────────────────────────────
@app.get("/predict")
def predict(lat: float, lon: float, hour: int, dow: int, station_id: int = None, administration_id: int = None):
    """
    Simple proximity + temporal risk score.
    dow: 0=Mon … 6=Sun
    Returns risk 0–100 with contributing factors.
    """
    df = get_detections(station_id, administration_id)
    if df.empty:
        return {"risk": 0, "factors": {}}

    # Distance weight: detections within 5 km contribute to risk
    df["dist_km"] = np.sqrt(
        ((df["latitude"].astype(float) - lat) * 111) ** 2 +
        ((df["longitude"].astype(float) - lon) * 111 * np.cos(np.radians(lat))) ** 2
    )
    nearby = df[df["dist_km"] <= 5.0]
    spatial_score = min(50, len(nearby) * 5)

    # Temporal: detections at same hour ±1, same dow ±1
    dow_mysql = (dow + 2) % 7  # remap back to MySQL DAYOFWEEK
    temporal = df[(df["hour"].between(max(0, hour - 1), min(23, hour + 1))) &
                  (df["dow"].isin([(dow_mysql % 7) + 1, ((dow_mysql + 1) % 7) + 1]))]
    temporal_score = min(30, len(temporal) * 3)

    # Escalation bonus from nearby incidents
    esc_score = min(20, int(nearby["escalation_level"].mean() * 5)) if not nearby.empty else 0

    risk = min(100, spatial_score + temporal_score + esc_score)

    return {
        "risk": risk,
        "level": "kritično" if risk >= 70 else ("visok" if risk >= 40 else ("umjeren" if risk >= 20 else "nizak")),
        "factors": {
            "prostorni": spatial_score,
            "vremenski": temporal_score,
            "eskalacijski": esc_score,
        },
        "nearby_count": len(nearby),
    }


# ── Border corridor (keeps the risk grid anchored to the actual state border) ─
# Real border geometry from Eurostat GISCO (CNTR_BN_01M_2024, EPSG:4326,
# https://gisco-services.ec.europa.eu/distribution/v2/countries/) — the
# HRV-BIH and SRB-HRV "INLAND" boundary linestrings, decimated ~3x/2x since
# the source resolution (~1:1M) is far finer than the grid's cell size.
BORDER_HR_BIH = [
    (42.9384, 17.5813), (42.9402, 17.5924), (42.9411, 17.6085), (42.9454, 17.6246), (42.9592, 17.6528), (42.9729, 17.7107),
    (43.0068, 17.6884), (43.0957, 17.6264), (43.1397, 17.5297), (43.1769, 17.4507), (43.1974, 17.4302), (43.2169, 17.429),
    (43.2459, 17.3947), (43.2648, 17.333), (43.2949, 17.3153), (43.3408, 17.2849), (43.3934, 17.2606), (43.4052, 17.256),
    (43.4338, 17.2834), (43.4789, 17.2676), (43.498, 17.224), (43.4965, 17.1855), (43.4948, 17.1522), (43.5178, 17.123),
    (43.555, 17.0525), (43.5667, 17.0255), (43.5822, 17.0053), (43.6013, 16.9828), (43.6316, 16.9505), (43.6747, 16.9077),
    (43.7053, 16.8718), (43.7324, 16.825), (43.7615, 16.8024), (43.7703, 16.7529), (43.8079, 16.7193), (43.8299, 16.7177),
    (43.8409, 16.7182), (43.8548, 16.7102), (43.8639, 16.6904), (43.8778, 16.6688), (43.8808, 16.6595), (43.8857, 16.6499),
    (43.898, 16.6323), (43.924, 16.6057), (43.947, 16.5723), (43.9845, 16.5379), (43.9979, 16.5232), (44.0244, 16.5022),
    (44.029, 16.4635), (44.0282, 16.4532), (44.0316, 16.4374), (44.0416, 16.4385), (44.0605, 16.439), (44.0823, 16.426),
    (44.0802, 16.3974), (44.1025, 16.3573), (44.1277, 16.3093), (44.1495, 16.3017), (44.1626, 16.2887), (44.1765, 16.264),
    (44.2093, 16.2253), (44.2149, 16.1837), (44.2403, 16.2177), (44.2671, 16.1938), (44.328, 16.1993), (44.3671, 16.177),
    (44.3766, 16.1446), (44.396, 16.1581), (44.4237, 16.1443), (44.4546, 16.1321), (44.4801, 16.1401), (44.4912, 16.131),
    (44.5038, 16.1228), (44.5195, 16.1151), (44.5378, 16.0614), (44.5785, 16.0184), (44.5893, 16.0333), (44.5976, 16.0464),
    (44.6078, 16.0525), (44.6129, 16.0549), (44.6278, 16.0456), (44.6372, 16.0416), (44.6558, 16.0162), (44.6782, 15.9685),
    (44.6906, 15.9646), (44.7102, 15.9545), (44.7456, 15.9008), (44.7352, 15.8832), (44.7343, 15.8626), (44.7227, 15.821),
    (44.7377, 15.8121), (44.7601, 15.7762), (44.7758, 15.7652), (44.81, 15.7418), (44.8243, 15.7299), (44.8307, 15.7632),
    (44.8595, 15.7747), (44.89, 15.7596), (44.9526, 15.7478), (44.9674, 15.7495), (44.9695, 15.7788), (45.0039, 15.7783),
    (45.0536, 15.7521), (45.0703, 15.7584), (45.0848, 15.7735), (45.0958, 15.7779), (45.1056, 15.7808), (45.1231, 15.7837),
    (45.1357, 15.7806), (45.1647, 15.7653), (45.1763, 15.7766), (45.1917, 15.7854), (45.1993, 15.8002), (45.2075, 15.8265),
    (45.218, 15.8278), (45.2236, 15.8478), (45.2175, 15.8929), (45.2253, 15.8886), (45.2252, 15.9117), (45.2229, 15.9262),
    (45.2117, 15.9284), (45.2253, 15.9701), (45.222, 15.9919), (45.2064, 16.0201), (45.1999, 16.0293), (45.1809, 16.016),
    (45.1761, 16.0466), (45.1596, 16.0554), (45.1325, 16.085), (45.1055, 16.0895), (45.0928, 16.1217), (45.0882, 16.1337),
    (45.0739, 16.1688), (45.0665, 16.1713), (45.0321, 16.193), (45.024, 16.2287), (45.0145, 16.2364), (45.0165, 16.2631),
    (45.0067, 16.2746), (44.9957, 16.2888), (44.9987, 16.3127), (45.0017, 16.326), (45.0108, 16.3572), (45.0503, 16.374),
    (45.0691, 16.3872), (45.0807, 16.3866), (45.1041, 16.395), (45.1205, 16.4091), (45.1266, 16.4365), (45.1626, 16.4788),
    (45.1927, 16.4854), (45.2246, 16.5256), (45.2218, 16.5427), (45.2225, 16.5802), (45.2236, 16.6179), (45.2143, 16.6324),
    (45.2033, 16.7195), (45.1867, 16.7988), (45.1946, 16.8425), (45.2135, 16.8355), (45.2152, 16.8536), (45.2049, 16.8531),
    (45.2188, 16.8659), (45.2247, 16.8691), (45.2302, 16.873), (45.2403, 16.88), (45.249, 16.8894), (45.2542, 16.894),
    (45.2717, 16.9213), (45.2514, 16.946), (45.2303, 16.9721), (45.2436, 16.966), (45.2382, 16.9826), (45.2249, 16.9975),
    (45.2358, 17.0081), (45.2265, 17.015), (45.2156, 17.0209), (45.2166, 17.0393), (45.1848, 17.0851), (45.1468, 17.1813),
    (45.1472, 17.2129), (45.155, 17.2551), (45.188, 17.2756), (45.176, 17.2911), (45.1654, 17.3127), (45.1543, 17.3287),
    (45.1352, 17.4143), (45.16, 17.441), (45.1408, 17.454), (45.1272, 17.4515), (45.1366, 17.4867), (45.1192, 17.4853),
    (45.1123, 17.4912), (45.1086, 17.5154), (45.1231, 17.5405), (45.1284, 17.553), (45.1091, 17.5566), (45.1161, 17.5755),
    (45.107, 17.6026), (45.1301, 17.6478), (45.1232, 17.686), (45.1109, 17.7157), (45.0859, 17.7634), (45.0678, 17.801),
    (45.0633, 17.8064), (45.0461, 17.8396), (45.0446, 17.8615), (45.0515, 17.8807), (45.0622, 17.9051), (45.0776, 17.9274),
    (45.098, 17.9302), (45.1139, 17.9586), (45.1311, 17.9767), (45.1515, 18.0087), (45.1306, 18.0302), (45.1355, 18.0438),
    (45.1382, 18.0734), (45.1139, 18.0786), (45.1033, 18.0819), (45.0831, 18.111), (45.0857, 18.1444), (45.0788, 18.1973),
    (45.1012, 18.2197), (45.1245, 18.2122), (45.1368, 18.2704), (45.1148, 18.2979), (45.1015, 18.3229), (45.109, 18.4194),
    (45.0846, 18.4432), (45.0668, 18.4693), (45.0591, 18.5173), (45.0523, 18.53), (45.0495, 18.5369), (45.0685, 18.5437),
    (45.0935, 18.5386), (45.08, 18.5668), (45.0708, 18.58), (45.0853, 18.5754), (45.092, 18.5853), (45.0805, 18.6106),
    (45.0947, 18.6229), (45.0848, 18.6342), (45.073, 18.6106), (45.0615, 18.6402), (45.0624, 18.6604), (45.0757, 18.6545),
    (45.0944, 18.6691), (45.0739, 18.6846), (45.0589, 18.6706), (45.0383, 18.7103), (45.0162, 18.7375), (44.9983, 18.7275),
    (44.9934, 18.7442), (44.9993, 18.7568), (44.9983, 18.7706), (44.9961, 18.7822), (44.9901, 18.7956), (44.9719, 18.7855),
    (44.9524, 18.7946), (44.9346, 18.7857), (44.9417, 18.7532), (44.9234, 18.7661), (44.9102, 18.7638), (44.8847, 18.8013),
    (44.8634, 18.8363), (44.8542, 18.861), (44.8531, 18.9953), (44.8554, 19.0221),
]

BORDER_HR_SRB = [
    (45.9212, 18.8897), (45.9115, 18.9034), (45.9005, 18.8755), (45.8842, 18.8782), (45.8745, 18.8998), (45.8594, 18.888),
    (45.8601, 18.8573), (45.815, 18.8482), (45.8077, 18.8737), (45.8213, 18.8836), (45.8236, 18.9055), (45.8047, 18.922),
    (45.7811, 18.905), (45.7808, 18.8831), (45.7541, 18.8938), (45.7454, 18.91), (45.7535, 18.9345), (45.7627, 18.9699),
    (45.7392, 18.9763), (45.724, 18.9482), (45.7275, 18.9264), (45.716, 18.9109), (45.7195, 18.9555), (45.7161, 18.9749),
    (45.6957, 18.9646), (45.6996, 18.9502), (45.7057, 18.9201), (45.6856, 18.9444), (45.6624, 18.973), (45.6499, 18.9695),
    (45.6436, 18.9419), (45.6323, 18.9364), (45.6334, 18.9453), (45.6209, 18.9447), (45.5982, 18.931), (45.5998, 18.9151),
    (45.5767, 18.8976), (45.5681, 18.914), (45.563, 18.9314), (45.5535, 18.9291), (45.5441, 18.9269), (45.5364, 18.9795),
    (45.5434, 18.9944), (45.5574, 19.0161), (45.5433, 19.0303), (45.5332, 19.0491), (45.5273, 19.0762), (45.5149, 19.0996),
    (45.4937, 19.093), (45.4852, 19.05), (45.4937, 19.0152), (45.4937, 19.0005), (45.4518, 18.9894), (45.4346, 19.0053),
    (45.414, 19.0301), (45.4012, 19.0174), (45.3983, 18.9841), (45.3807, 18.9705), (45.3588, 19.0018), (45.3516, 19.0252),
    (45.3429, 19.0557), (45.3411, 19.0753), (45.3272, 19.0972), (45.3119, 19.0979), (45.2884, 19.1273), (45.279, 19.1482),
    (45.2756, 19.1611), (45.2734, 19.17), (45.2669, 19.1854), (45.2711, 19.2426), (45.279, 19.2528), (45.2774, 19.2723),
    (45.2614, 19.2668), (45.2452, 19.2598), (45.2366, 19.2884), (45.232, 19.3507), (45.233, 19.4195), (45.2185, 19.4269),
    (45.1965, 19.4462), (45.1873, 19.4291), (45.1735, 19.4346), (45.1662, 19.4177), (45.1727, 19.3885), (45.1742, 19.3639),
    (45.1651, 19.3507), (45.1778, 19.3318), (45.2071, 19.3167), (45.196, 19.2872), (45.183, 19.289), (45.1727, 19.2928),
    (45.1738, 19.2775), (45.1748, 19.2658), (45.1766, 19.2382), (45.1843, 19.2272), (45.2011, 19.1902), (45.1988, 19.1705),
    (45.187, 19.1748), (45.1797, 19.1929), (45.1652, 19.186), (45.1329, 19.1273), (45.1394, 19.1042), (45.1386, 19.0869),
    (45.1153, 19.077), (45.1025, 19.0922), (45.0911, 19.0929), (45.079, 19.1054), (45.0718, 19.1018), (45.0545, 19.1012),
    (45.0456, 19.1009), (45.0311, 19.0988), (45.0175, 19.0996), (44.9983, 19.0937), (44.9855, 19.0747), (44.9719, 19.0745),
    (44.9719, 19.0988), (44.9719, 19.1126), (44.976, 19.1199), (44.9801, 19.1381), (44.9719, 19.1474), (44.9572, 19.1539),
    (44.9476, 19.1448), (44.9395, 19.1326), (44.9384, 19.1249), (44.9213, 19.0848), (44.9177, 19.0868), (44.9092, 19.0853),
    (44.9115, 19.046), (44.9216, 19.0332), (44.9153, 18.9976), (44.9057, 18.9894), (44.8947, 18.9925), (44.8852, 19.0017),
    (44.8554, 19.0221),
]


def _border_segments():
    segs = []
    for line in (BORDER_HR_BIH, BORDER_HR_SRB):
        for i in range(len(line) - 1):
            segs.append((line[i], line[i + 1]))
    return segs


_BORDER_SEGMENTS = _border_segments()
_SEG_A = np.array([s[0] for s in _BORDER_SEGMENTS])  # (N,2) lat,lon
_SEG_B = np.array([s[1] for s in _BORDER_SEGMENTS])
_SEG_COS_MID = np.cos(np.radians((_SEG_A[:, 0] + _SEG_B[:, 0]) / 2))  # (N,)
_SEG_DX = (_SEG_B[:, 1] - _SEG_A[:, 1]) * 111.0 * _SEG_COS_MID  # (N,) km
_SEG_DY = (_SEG_B[:, 0] - _SEG_A[:, 0]) * 111.0  # (N,) km
_SEG_LEN2 = np.maximum(_SEG_DX ** 2 + _SEG_DY ** 2, 1e-9)


def dist_to_border_km(lats: np.ndarray, lons: np.ndarray) -> np.ndarray:
    """Vectorized min distance (km) from each (lat, lon) point to the border polyline."""
    px = (lons[:, None] - _SEG_A[None, :, 1]) * 111.0 * _SEG_COS_MID[None, :]
    py = (lats[:, None] - _SEG_A[None, :, 0]) * 111.0
    t = np.clip((px * _SEG_DX[None, :] + py * _SEG_DY[None, :]) / _SEG_LEN2[None, :], 0, 1)
    dist = np.hypot(px - t * _SEG_DX[None, :], py - t * _SEG_DY[None, :])
    return dist.min(axis=1)


def border_crossing_bearing(lat: float, lon: float, station_points: np.ndarray) -> float:
    """
    Compass bearing (0=N, 90=E, 180=S, 270=W) pointing from the border
    segment nearest to (lat, lon) toward the Croatian side.

    The HR-BiH/HR-SRB border winds in every direction, so "into Croatia"
    isn't reliably "up" on the map — a station whose territory runs
    roughly north-south needs an arrow pointing east/west, not north.
    We find the nearest border segment, take its perpendicular, and pick
    whichever of the two perpendicular directions points toward the
    nearest known Croatian border-station (a reliable Croatia-side anchor).
    """
    px = (lon - _SEG_A[:, 1]) * 111.0 * _SEG_COS_MID
    py = (lat - _SEG_A[:, 0]) * 111.0
    t = np.clip((px * _SEG_DX + py * _SEG_DY) / _SEG_LEN2, 0, 1)
    dist = np.hypot(px - t * _SEG_DX, py - t * _SEG_DY)
    i = int(np.argmin(dist))

    seg_dx, seg_dy = _SEG_DX[i], _SEG_DY[i]
    n1 = np.array([-seg_dy, seg_dx])
    n2 = np.array([seg_dy, -seg_dx])

    mid_lat = (_SEG_A[i, 0] + _SEG_B[i, 0]) / 2
    mid_lon = (_SEG_A[i, 1] + _SEG_B[i, 1]) / 2
    cos_mid = np.cos(np.radians(mid_lat))

    if station_points.shape[0] > 0:
        d_east = (station_points[:, 1] - mid_lon) * 111.0 * cos_mid
        d_north = (station_points[:, 0] - mid_lat) * 111.0
        k = int(np.argmin(d_east ** 2 + d_north ** 2))
        v = np.array([d_east[k], d_north[k]])
    else:
        v = n1

    normal = n1 if np.dot(n1, v) >= np.dot(n2, v) else n2
    norm = float(np.hypot(normal[0], normal[1]))
    if norm < 1e-9:
        return 0.0
    east, north = normal / norm
    return round(float(np.degrees(np.arctan2(east, north))) % 360, 1)


# ── Predictive risk grid (presence-only model, MaxEnt-style) ─────────────────
def _encode_time(df: pd.DataFrame) -> pd.DataFrame:
    hour_rad = df["hour"].astype(float) / 24 * 2 * np.pi
    dow_rad = df["dow"].astype(float) / 7 * 2 * np.pi
    return pd.DataFrame({
        "lat": df["lat"].astype(float),
        "lon": df["lon"].astype(float),
        "hour_sin": np.sin(hour_rad),
        "hour_cos": np.cos(hour_rad),
        "dow_sin": np.sin(dow_rad),
        "dow_cos": np.cos(dow_rad),
    })


def _train_presence_model(df: pd.DataFrame, bbox: tuple) -> RandomForestClassifier:
    """
    Real detections = positive class; random space/time samples = background class.
    Same technique used for presence-only species-distribution modelling (MaxEnt),
    which fits this problem well since we only ever observe *where incidents happened*,
    never confirmed "nothing happened here" negatives.
    """
    lat_min, lat_max, lon_min, lon_max = bbox
    pos_src = df.dropna(subset=["hour", "dow"])
    rng = np.random.default_rng(42)
    n_pos = len(pos_src)
    n_bg = max(n_pos * 4, 300)

    pos = pd.DataFrame({
        "lat": pos_src["latitude"].astype(float),
        "lon": pos_src["longitude"].astype(float),
        "hour": pos_src["hour"].astype(float),
        "dow": pos_src["dow"].astype(float),
        "weight": 1 + pos_src["escalation_level"].astype(float) * 0.5,
        "label": 1,
    })
    bg = pd.DataFrame({
        "lat": rng.uniform(lat_min, lat_max, n_bg),
        "lon": rng.uniform(lon_min, lon_max, n_bg),
        "hour": rng.integers(0, 24, n_bg).astype(float),
        "dow": rng.integers(1, 8, n_bg).astype(float),
        "weight": 1.0,
        "label": 0,
    })

    data = pd.concat([pos, bg], ignore_index=True)
    model = RandomForestClassifier(
        n_estimators=200, max_depth=8, min_samples_leaf=4, random_state=42, n_jobs=-1
    )
    model.fit(_encode_time(data), data["label"], sample_weight=data["weight"])
    return model


@app.get("/risk-grid")
def risk_grid(station_id: int = None, administration_id: int = None,
              hour: int = None, dow: int = None, cell_km: float = None,
              corridor_km: float = 20.0):
    """
    Trains a spatio-temporal presence-only risk model on historical detections and
    scores a grid over the covered area, comparing predicted risk against how much
    drone-flight coverage each cell has already had — surfacing under-watched
    high-risk cells and over-watched low-risk cells. The grid is clipped to a
    corridor around the actual HR–BiH / HR–SRB border so results stay anchored
    to the border line instead of drifting into the interior.
    """
    df = get_detections(station_id, administration_id)
    if len(df) < 8:
        return {"cells": [], "recommend_increase": [], "recommend_decrease": [],
                "message": "Nedovoljno detekcija za treniranje modela (potrebno min. 8)"}

    lat_min, lat_max = float(df["latitude"].min()), float(df["latitude"].max())
    lon_min, lon_max = float(df["longitude"].min()), float(df["longitude"].max())
    pad_lat = max((lat_max - lat_min) * 0.2, 0.03)
    pad_lon = max((lon_max - lon_min) * 0.2, 0.03)
    bbox = (lat_min - pad_lat, lat_max + pad_lat, lon_min - pad_lon, lon_max + pad_lon)
    mid_lat = (bbox[0] + bbox[1]) / 2

    if cell_km is None:
        extent_km = max(
            (bbox[1] - bbox[0]) * 111.0,
            (bbox[3] - bbox[2]) * 111.0 * max(np.cos(np.radians(mid_lat)), 0.2),
        )
        cell_km = max(1.0, extent_km / 22)

    lat_step = cell_km / 111.0
    lon_step = cell_km / (111.0 * max(np.cos(np.radians(mid_lat)), 0.2))
    lats = np.arange(bbox[0], bbox[1], lat_step)
    lons = np.arange(bbox[2], bbox[3], lon_step)
    if len(lats) == 0 or len(lons) == 0 or len(lats) * len(lons) > 2500:
        return {"cells": [], "recommend_increase": [], "recommend_decrease": [],
                "message": "Područje je preveliko za odabranu veličinu ćelije"}

    model = _train_presence_model(df, bbox)

    grid_lat, grid_lon = np.meshgrid(lats, lons, indexing="ij")
    flat_lat, flat_lon = grid_lat.ravel(), grid_lon.ravel()

    # Clip to the border corridor so cells drifting into the interior (an
    # artifact of the rectangular padded bbox) are dropped.
    border_dist = dist_to_border_km(flat_lat, flat_lon)
    keep_mask = border_dist <= corridor_km
    if not keep_mask.any():
        return {"cells": [], "recommend_increase": [], "recommend_decrease": [],
                "message": "Nema ćelija unutar zadanog pojasa uz granicu"}

    lat_idx_full, lon_idx_full = np.meshgrid(np.arange(len(lats)), np.arange(len(lons)), indexing="ij")
    keep_lat_idx = lat_idx_full.ravel()[keep_mask]
    keep_lon_idx = lon_idx_full.ravel()[keep_mask]
    flat_lat, flat_lon = flat_lat[keep_mask], flat_lon[keep_mask]
    n_cells = len(flat_lat)

    if hour is not None and dow is not None:
        dow_mysql = float(((dow + 2) % 7) + 1)
        time_combos = [(float(hour), dow_mysql)]
    else:
        time_combos = [(h, d) for h in (2, 8, 14, 20) for d in range(1, 8)]

    risk_acc = np.zeros(n_cells)
    for h, d in time_combos:
        q = pd.DataFrame({"lat": flat_lat, "lon": flat_lon, "hour": h, "dow": d})
        risk_acc += model.predict_proba(_encode_time(q))[:, 1]
    risk = risk_acc / len(time_combos)
    risk = risk / max(risk.max(), 1e-9) * 100

    # Coverage: how much drone-flight history each cell already has
    flights_df = get_flight_points(station_id, administration_id)
    coverage_grid = np.zeros((len(lats), len(lons)))
    if not flights_df.empty:
        f_lat_idx = np.clip(((flights_df["latitude"].astype(float) - bbox[0]) / lat_step).astype(int), 0, len(lats) - 1)
        f_lon_idx = np.clip(((flights_df["longitude"].astype(float) - bbox[2]) / lon_step).astype(int), 0, len(lons) - 1)
        np.add.at(coverage_grid, (f_lat_idx, f_lon_idx), 1)
        cov_log = np.log1p(coverage_grid)
        if cov_log.max() > 0:
            coverage_grid = cov_log / cov_log.max() * 100
    coverage = coverage_grid[keep_lat_idx, keep_lon_idx]

    cells = [
        {
            "lat": round(float(flat_lat[i]), 5),
            "lon": round(float(flat_lon[i]), 5),
            "risk": round(float(risk[i]), 1),
            "coverage": round(float(coverage[i]), 1),
            "gap": round(float(risk[i] - coverage[i]), 1),
        }
        for i in range(n_cells)
    ]

    increase = sorted((c for c in cells if c["risk"] >= 35), key=lambda c: c["gap"], reverse=True)[:8]
    decrease = sorted((c for c in cells if c["coverage"] >= 35 and c["risk"] < 20), key=lambda c: c["gap"])[:8]

    # Bearing (toward Croatia, perpendicular to the local border) for the map
    # arrow markers — only computed for the handful of shortlisted cells.
    station_points = get_station_points()
    for c in increase:
        c["bearing"] = border_crossing_bearing(c["lat"], c["lon"], station_points)

    return {
        "cell_km": round(float(cell_km), 2),
        "cells": cells,
        "recommend_increase": increase,
        "recommend_decrease": decrease,
        "trained_on": int(len(df)),
        "border": {
            "hr_bih": [list(p) for p in BORDER_HR_BIH],
            "hr_srb": [list(p) for p in BORDER_HR_SRB],
        },
    }
