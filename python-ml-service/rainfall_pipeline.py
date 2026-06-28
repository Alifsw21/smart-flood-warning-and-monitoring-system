"""Training and inference helpers for rainfall (curah hujan) prediction."""

from __future__ import annotations

from datetime import datetime, timezone

import pandas as pd
from sklearn.ensemble import RandomForestClassifier

RANDOM_STATE = 42
CV_FOLDS = 5

WEATHER_COLUMNS = ["Tn", "Tx", "Tavg", "RH_avg", "ss", "ff_x", "ddd_x", "ff_avg"]
CALENDAR_COLUMNS = ["month", "is_rainy_season", "rh_ss"]
TRAINING_FEATURES = WEATHER_COLUMNS + CALENDAR_COLUMNS

RAIN_CATEGORIES = [
    {
        "id": "kering",
        "label": "Cerah/Berawan",
        "rr_min": 0.0,
        "rr_max": 5.0,
        "midpoint_mm": 2.5,
    },
    {
        "id": "sedang",
        "label": "Hujan Sedang",
        "rr_min": 5.0,
        "rr_max": 50.0,
        "midpoint_mm": 25.0,
    },
    {
        "id": "lebat",
        "label": "Hujan Lebat",
        "rr_min": 50.0,
        "rr_max": 10_000.0,
        "midpoint_mm": 75.0,
    },
]

CATEGORY_BY_ID = {item["id"]: item for item in RAIN_CATEGORIES}


def rainy_season(month: int) -> int:
    return 1 if month in {10, 11, 12, 1, 2, 3} else 0


def rr_to_category_id(value: float) -> str:
    if value <= 5:
        return "kering"
    if value <= 50:
        return "sedang"
    return "lebat"


def prepare_training_frame(csv_path: str) -> pd.DataFrame:
    df = pd.read_csv(csv_path)
    for column in WEATHER_COLUMNS:
        df[column] = df[column].fillna(df[column].median())
    df["RR"] = df["RR"].fillna(0)
    df["date"] = pd.to_datetime(df["date"], errors="coerce")
    df["month"] = df["date"].dt.month.fillna(1).astype(int)
    df["is_rainy_season"] = df["month"].map(rainy_season).astype(int)
    df["rh_ss"] = df["RH_avg"] * df["ss"]
    df["category_id"] = df["RR"].map(rr_to_category_id)
    return df


def weather_vector_from_api(
    *,
    suhu_min: float,
    suhu_max: float,
    suhu_rata_rata: float,
    kelembapan_udara: float,
    sun_shine: float,
    kecepatan_angin: float,
    arah_angin: float,
    kecepatan_rata_rata_angin: float,
    month: int | None = None,
) -> dict[str, float]:
    when = datetime.now(timezone.utc)
    resolved_month = month if month is not None else when.month
    return {
        "Tn": suhu_min,
        "Tx": suhu_max,
        "Tavg": suhu_rata_rata,
        "RH_avg": kelembapan_udara,
        "ss": sun_shine,
        "ff_x": kecepatan_angin,
        "ddd_x": arah_angin,
        "ff_avg": kecepatan_rata_rata_angin,
        "month": float(resolved_month),
        "is_rainy_season": float(rainy_season(resolved_month)),
        "rh_ss": kelembapan_udara * sun_shine,
    }


def frame_from_api_vector(vector: dict[str, float]) -> pd.DataFrame:
    return pd.DataFrame([[vector[name] for name in TRAINING_FEATURES]], columns=TRAINING_FEATURES)


def train_rainfall_bundle(csv_path: str) -> dict:
    df = prepare_training_frame(csv_path)
    classifier = RandomForestClassifier(
        n_estimators=400,
        max_depth=18,
        min_samples_leaf=1,
        random_state=RANDOM_STATE,
        n_jobs=-1,
    )
    classifier.fit(df[TRAINING_FEATURES], df["category_id"])

    return {
        "version": 2,
        "model_type": "rainfall_category_classifier",
        "classifier": classifier,
        "features": TRAINING_FEATURES,
        "categories": RAIN_CATEGORIES,
        "metric": "accuracy",
    }


def is_rainfall_bundle(model: object) -> bool:
    return isinstance(model, dict) and model.get("model_type") == "rainfall_category_classifier"


def predict_rainfall_mm(model: object, vector: dict[str, float]) -> tuple[float, str, str, float]:
    frame = frame_from_api_vector(vector)

    if is_rainfall_bundle(model):
        category_id = str(model["classifier"].predict(frame)[0])
        probs = model["classifier"].predict_proba(frame)[0]
        classes = list(model["classifier"].classes_)
        confidence = float(probs[classes.index(category_id)])
        meta = CATEGORY_BY_ID[category_id]
        return meta["midpoint_mm"], meta["label"], category_id, confidence

    raw = float(model.predict(frame[WEATHER_COLUMNS])[0])
    mm = max(0.0, raw)
    category_id = rr_to_category_id(mm)
    return mm, CATEGORY_BY_ID[category_id]["label"], category_id, 0.0


def rainfall_feature_importance(model: object) -> dict[str, float]:
    if is_rainfall_bundle(model):
        estimator = model["classifier"]
        names = model["features"]
    elif hasattr(model, "feature_importances_"):
        estimator = model
        names = WEATHER_COLUMNS
    else:
        return {name: 0.0 for name in TRAINING_FEATURES}

    if not hasattr(estimator, "feature_importances_"):
        return {name: 0.0 for name in names}

    return {
        name: round(float(value), 4)
        for name, value in zip(names, estimator.feature_importances_.tolist())
    }
