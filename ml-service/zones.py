# Postupak s DBSCAN žarištima: označavanje žarišta (K1, K2, ...), povezivanje žarišta s letovima,
# izgradnja skupa podataka (žarište*dan*vremenski blok), usporedba četiri modela
# s kronološkom podjelom podataka i procjena rizika najboljim modelom.
import numpy as np
import pandas as pd
from sklearn.cluster import DBSCAN
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.neighbors import KNeighborsClassifier
from sklearn.preprocessing import StandardScaler
from sklearn.utils.class_weight import compute_sample_weight

from ml_common import evaluate_model

# Vremenski blokovi, isti kao u config/surveillance.php u Laravelu (treba ih ručno uskladiti)
TIME_BLOCKS = [
    {"label": "00:00–04:00", "start": 0, "end": 4},
    {"label": "04:00–08:00", "start": 4, "end": 8},
    {"label": "08:00–12:00", "start": 8, "end": 12},
    {"label": "12:00–16:00", "start": 12, "end": 16},
    {"label": "16:00–20:00", "start": 16, "end": 20},
    {"label": "20:00–24:00", "start": 20, "end": 24},
]
MIN_FLIGHTS_FOR_ZONE = 5  # isto kao min_flights_for_zone u config/surveillance.php

DOW_LABELS_HR = {0: "ponedjeljkom", 1: "utorkom", 2: "srijedom", 3: "četvrtkom",
                 4: "petkom", 5: "subotom", 6: "nedjeljom"}  # 0 = ponedjeljak


# Vraća redni broj vremenskog bloka za zadani sat
def _block_for_hour(hour: int) -> int:
    for i, b in enumerate(TIME_BLOCKS):
        if b["start"] <= hour < b["end"]:
            return i
    return len(TIME_BLOCKS) - 1


# 1. korak: izdvajanje žarišta
# DBSCAN se pokreće nad svim detekcijama, a žarišta se označavaju K1, K2, ... prema broju detekcija.
# point_zone_ids za svaku detekciju čuva oznaku žarišta (None ako ne pripada nijednom),
# i dalje se koristi ta oznaka, a ne provjera udaljenosti od središta žarišta.
def compute_zones(df: pd.DataFrame, eps_km: float = 2.0, min_samples: int = 3) -> dict:
    if len(df) < min_samples:
        return {"zones": [], "noise": [], "total_detections": len(df), "point_zone_ids": [None] * len(df)}

    # haversine radi u radijanima, pa se i eps pretvara u radijane (polumjer Zemlje 6371 km)
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

        # najaktivniji dan i vremenski blok u žarištu
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

    # oznake K1, K2, ... prema broju detekcija, od najvećeg
    raw.sort(key=lambda z: z["count"], reverse=True)
    zones = []
    raw_cid_to_kid = {}
    for i, z in enumerate(raw):
        z["id"] = f"K{i + 1}"
        raw_cid_to_kid[z.pop("_raw_cid")] = z["id"]
        zones.append(z)

    point_zone_ids = [raw_cid_to_kid.get(c) for c in labels]  # šum (-1) ostaje None

    noise_pts = df[df.cluster == -1][["latitude", "longitude"]].values.tolist()
    return {"zones": zones, "noise": noise_pts, "total_detections": len(df), "point_zone_ids": point_zone_ids}


# 2. korak: žarišta i letovi
# Za svako žarište: broj letova i sati nadzora, broj detekcija, detekcije po satu leta,
# najaktivniji termin i trend (zadnjih 30 dana prema prethodnih 30).
# flight_zone (koji let prolazi kroz koje žarište) računa se SQL upitom u main.py,
# jer bi učitavanje svih GPS točaka u memoriju bilo presporo.
def zone_flight_stats(zones: list, detections_df: pd.DataFrame, flights_meta: pd.DataFrame,
                       flight_zone: pd.DataFrame, recent_days: int = 30) -> list:
    if not zones:
        return []

    det = detections_df.copy()

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

        # trend: zadnjih 30 dana u odnosu na prethodnih 30
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


# 3. korak: skup podataka za nadzirano učenje
# Jedan redak za svaku kombinaciju žarišta, dana i vremenskog bloka kroz cijelo razdoblje.
# Sva obilježja pomaknuta su za jedan dan unatrag, da model ne vidi ishod koji predviđa.
# Oznaka je 1 ako je u tom žarištu u tom bloku stvarno zabilježena detekcija, inače 0.
def build_zone_timeline_dataset(zones: list, detections_df: pd.DataFrame,
                                 flights_meta: pd.DataFrame, flight_zone: pd.DataFrame) -> pd.DataFrame:
    if not zones:
        return pd.DataFrame()

    det = detections_df.copy()
    det = det.dropna(subset=["zone"])
    det["detected_at"] = pd.to_datetime(det["detected_at"])
    det["date"] = det["detected_at"].dt.normalize()
    det["block"] = det["hour"].astype(int).map(_block_for_hour)

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

        # dnevni broj detekcija, letova i minuta leta za žarište
        daily_det = zdet.groupby("date").size().reindex(day_range, fill_value=0)
        daily_flights = zfz.groupby("date")["flight_id"].nunique().reindex(day_range, fill_value=0)
        daily_minutes = zfz.groupby("date")["duration_minutes"].sum().reindex(day_range, fill_value=0)

        # detekcije po danu i bloku, iz toga se dobiva oznaka
        block_pivot = zdet.groupby(["date", "block"]).size().unstack(fill_value=0)
        block_pivot = block_pivot.reindex(day_range, fill_value=0)
        for b in range(len(TIME_BLOCKS)):
            if b not in block_pivot.columns:
                block_pivot[b] = 0
        block_pivot = block_pivot[sorted(block_pivot.columns)]

        no_det_day = (daily_det == 0).astype(int)
        daily_flights_no_det = daily_flights * no_det_day

        # kumulativna obilježja i obilježja za zadnjih 7 i 30 dana, sve do prethodnog dana (shift(1))
        flights_cum = daily_flights.cumsum().shift(1).fillna(0)
        minutes_cum = daily_minutes.cumsum().shift(1).fillna(0)
        dets_cum = daily_det.cumsum().shift(1).fillna(0)
        flights_no_det_cum = daily_flights_no_det.cumsum().shift(1).fillna(0)

        dets_7d = daily_det.rolling(7, min_periods=1).sum().shift(1).fillna(0)
        dets_30d = daily_det.rolling(30, min_periods=1).sum().shift(1).fillna(0)
        flights_30d = daily_flights.rolling(30, min_periods=1).sum().shift(1).fillna(0)
        minutes_30d = daily_minutes.rolling(30, min_periods=1).sum().shift(1).fillna(0)
        prev_30d = dets_30d.shift(30).fillna(0)

        # broj dana od zadnje detekcije
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
                continue  # prvih 30 dana nema dovoljno povijesti za obilježja
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


# Matrica obilježja: sat, dan i mjesec kao sinus i kosinus, povijesna obilježja i oznaka žarišta (one-hot)
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


# Modeli koji se uspoređuju (ključ, naziv, treba li standardizacija, model)
MODEL_SPECS = [
    ("logistic_regression", "Logistic Regression", True, lambda: LogisticRegression(max_iter=1000)),
    ("knn", "k-Nearest Neighbors", True, lambda: KNeighborsClassifier(n_neighbors=15)),
    ("random_forest", "Random Forest", False, lambda: RandomForestClassifier(
        n_estimators=200, max_depth=8, min_samples_leaf=4, random_state=42, n_jobs=-1)),
    ("gradient_boosting", "Gradient Boosting", False, lambda: GradientBoostingClassifier(
        n_estimators=150, max_depth=3, random_state=42)),
]


# Pokrivenost top-K žarišta: u svakom terminu žarišta se poredaju po procjeni modela
# i gleda se koliki je udio stvarnih detekcija bio u K najrizičnijih žarišta.
# Ne ovisi o pragu odluke.
def topk_zone_coverage(dataset_slice: pd.DataFrame, y_proba, k_values=(5, 10)) -> dict:
    d = dataset_slice[["date", "block", "label"]].copy()
    d["proba"] = np.asarray(y_proba)
    total_positive = int(d["label"].sum())
    if total_positive == 0:
        return {f"coverage_at_{k}": None for k in k_values} | {"total_positive_events": 0}

    result = {"total_positive_events": total_positive}
    for k in k_values:
        def _covered(group):
            top = group.sort_values("proba", ascending=False).head(k)
            return top["label"].sum()
        covered = d.groupby(["date", "block"], sort=False).apply(_covered, include_groups=False).sum()
        result[f"coverage_at_{k}"] = round(float(covered) / total_positive, 3)
    return result


# 4. korak: usporedba četiri modela
def compare_zone_models(dataset: pd.DataFrame) -> dict:
    if dataset.empty or dataset["label"].sum() < 5:
        return {"trained": False, "message": "Nedovoljno pozitivnih primjera za pouzdanu procjenu modela."}

    dataset = dataset.sort_values("date").reset_index(drop=True)
    # Kronološka podjela 60/20/20: učenje, validacija (odabir praga) i test, koji se čita samo jednom
    train_cutoff = int(len(dataset) * 0.6)
    val_cutoff = int(len(dataset) * 0.8)
    X, y = _feature_matrix(dataset)
    X_train, X_val, X_test = X.iloc[:train_cutoff], X.iloc[train_cutoff:val_cutoff], X.iloc[val_cutoff:]
    y_train, y_val, y_test = y.iloc[:train_cutoff], y.iloc[train_cutoff:val_cutoff], y.iloc[val_cutoff:]
    test_slice = dataset.iloc[val_cutoff:]

    if y_train.nunique() < 2 or y_test.nunique() < 2:
        return {"trained": False, "message": "Kronološki test skup nema oba razreda — premalo podataka za pouzdanu procjenu."}

    # težine primjera zbog neuravnoteženih razreda, standardizacija samo na skupu za učenje
    w_train = compute_sample_weight("balanced", y_train)
    scaler = StandardScaler().fit(X_train)
    X_train_scaled = pd.DataFrame(scaler.transform(X_train), columns=X_train.columns, index=X_train.index)
    X_val_scaled = pd.DataFrame(scaler.transform(X_val), columns=X_val.columns, index=X_val.index)
    X_test_scaled = pd.DataFrame(scaler.transform(X_test), columns=X_test.columns, index=X_test.index)

    results = {}
    for key, label, needs_scaling, make_model in MODEL_SPECS:
        xtr = X_train_scaled if needs_scaling else X_train
        xv = X_val_scaled if needs_scaling else X_val
        xte = X_test_scaled if needs_scaling else X_test
        metrics, y_proba_test, _ = evaluate_model(
            make_model(), xtr, y_train, w_train, xte, y_test,
            feature_names=list(X.columns), X_val=xv, y_val=y_val, return_artifacts=True,
        )
        metrics["topk_coverage"] = topk_zone_coverage(test_slice, y_proba_test)
        results[key] = {"label": label, **metrics}

    # najbolji model po F1, a kod jednakog F1 po ROC-AUC
    best_key = max(results, key=lambda k: (results[k]["f1"], results[k]["roc_auc"] or 0))

    return {
        "trained": True,
        "n_rows": len(dataset), "n_positive": int(y.sum()), "n_zones": int(dataset["zone"].nunique()),
        "train_size": len(X_train), "val_size": len(X_val), "test_size": len(X_test),
        "val_date": dataset.iloc[train_cutoff]["date"] if train_cutoff < len(dataset) else dataset.iloc[-1]["date"],
        "split_date": dataset.iloc[val_cutoff]["date"] if val_cutoff < len(dataset) else dataset.iloc[-1]["date"],
        "models": results,
        "best_model": best_key,
    }


# Učenje svih modela traje nekoliko minuta, pa se rezultat sprema u memoriju.
# Čuva se do 8 rezultata, po jedan za svaki opseg podataka (postaja, uprava, cijela mreža).
_MODEL_CACHE_MAX = 8
_model_comparison_cache: dict = {}


def compare_zone_models_cached(dataset: pd.DataFrame) -> dict:
    # ključ se mijenja čim se promijene podaci
    key = (len(dataset), dataset["date"].max() if not dataset.empty else None, int(dataset["label"].sum()) if not dataset.empty else 0)
    if key in _model_comparison_cache:
        return _model_comparison_cache[key]
    result = compare_zone_models(dataset)
    if len(_model_comparison_cache) >= _MODEL_CACHE_MAX:
        _model_comparison_cache.pop(next(iter(_model_comparison_cache)))  # briše se najstariji rezultat
    _model_comparison_cache[key] = result
    return result


# 5. korak: indeks rizika za svako žarište
# Odabrani model uči se na svim podacima, a zatim procjenjuje rizik za zadani dan i blok
# (ako nisu zadani, za trenutni). Žarišta bez dovoljno letova ne dobivaju procjenu.
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

    # za svako žarište uzimaju se njegova najnovija obilježja
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

        # vrijeme se postavlja na traženi dan i blok
        row = latest.loc[zid].copy()
        row["zone"] = zid  # set_index je maknuo stupac zone, pa se vraća
        row["hour"] = target_hour
        row["dow"] = target_dow
        row["month"] = target_month
        row["block"] = target_block
        row_df = pd.DataFrame([row])
        Xq, _ = _feature_matrix(row_df)
        Xq = Xq.reindex(columns=X.columns, fill_value=0)
        if scaler is not None:
            Xq = pd.DataFrame(scaler.transform(Xq), columns=Xq.columns)

        # indeks rizika 0-100 i boja na karti
        proba = round(float(model.predict_proba(Xq)[0, 1]) * 100, 1)
        tier = "red" if proba >= 70 else "orange" if proba >= 50 else "yellow" if proba >= 25 else "green"

        results.append({**base, "probability": proba, "tier": tier})

    return results
