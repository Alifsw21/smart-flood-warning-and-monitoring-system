from pathlib import Path
from typing import Any, Literal

import joblib
import numpy as np
from pydantic import BaseModel, Field

from rainfall_pipeline import (
    predict_rainfall_mm,
    rainfall_feature_importance,
    weather_vector_from_api,
)

MODELS_DIR = Path(__file__).resolve().parent / "models"

ANOMALY_FEATURES = ["sensor_value", "timestamp_hour", "rolling_mean_1h", "z_score"]

WATER_LEVEL_FEATURES = ['curahHujan', 'tinggiAir', 'kelembapanTanah']
WEATHER_FEATURES = ['suhuMin', 'suhuMax', 'suhuRataRata', 'kelembapanUdara', 'sunShine', 'kecepatanAngin', 'arahAngin', 'kecepatanRataRataAngin']
RAINFALL_FEATURES = WEATHER_FEATURES + ["month", "is_rainy_season", "rh_ss"]

_model_water_level = None
_model_weather = None
_model_rainfall = None
_model_anomaly = None
_load_error = None
_feature_importance_cache: dict | None = None


class BanjirInput(BaseModel):
    idSungai: int = Field(..., ge=1)
    curahHujan: float = Field(0.0, ge=0)
    tinggiAir: float = Field(0.0, ge=0)
    kelembapanTanah: float = Field(0.0, ge=0, le=100)
    suhuMin: float = Field(25.0)
    suhuMax: float = Field(32.0)
    suhuRataRata: float = Field(28.0)
    kelembapanUdara: float = Field(77.0, ge=0, le=100)
    sunShine: float = Field(0.0, ge=0)
    kecepatanAngin: float = Field(0.0, ge=0)
    arahAngin: float = Field(0.0, ge=0, le=360)
    kecepatanRataRataAngin: float = Field(0.0, ge=0)


class CurahHujanInput(BaseModel):
    idNode: int = Field(..., ge=1)
    tinggiAir: float = Field(0.0, ge=0)
    kelembapanTanah: float = Field(0.0, ge=0, le=100)
    suhuMin: float = Field(25.0)
    suhuMax: float = Field(32.0)
    suhuRataRata: float = Field(28.0)
    kelembapanUdara: float = Field(77.0, ge=0, le=100)
    sunShine: float = Field(0.0, ge=0)
    kecepatanAngin: float = Field(0.0, ge=0)
    arahAngin: float = Field(0.0, ge=0, le=360)
    kecepatanRataRataAngin: float = Field(0.0, ge=0)


class SensorAnomalyInput(BaseModel):
    sensor_value: float
    timestamp_hour: int = Field(..., ge=0, le=23)
    rolling_mean_1h: float
    z_score: float


class BatchPredictItem(BaseModel):
    type: Literal['traffic', 'air-quality', 'anomaly']
    payload: dict[str, Any]


class BatchPredictRequest(BaseModel):
    items: list[BatchPredictItem] = Field(..., min_length=1)


def _build_feature_importance_cache() -> dict:
    return {
        "traffic": _tree_feature_importance(_model_water_level, WATER_LEVEL_FEATURES),
        "air_quality": _tree_feature_importance(_model_weather, WEATHER_FEATURES),
        "rainfall": rainfall_feature_importance(_model_rainfall),
        "anomaly": {name: 0.0 for name in ANOMALY_FEATURES},
    }


def load_models():
    global _model_water_level, _model_weather, _model_rainfall, _model_anomaly, _load_error, _feature_importance_cache

    if _model_water_level is not None:
        return

    try:
        _model_water_level = joblib.load(MODELS_DIR / "deteksi_banjir_berdasarkan_waterLevel.pkl")
        _model_weather = joblib.load(MODELS_DIR / "deteksi_banjir_berdasarkan_cuaca.pkl")
        _model_rainfall = joblib.load(MODELS_DIR / "prediksi_curah_hujan.pkl")
        anomaly_path = MODELS_DIR / "deteksi_anomali.pkl"
        _model_anomaly = joblib.load(anomaly_path) if anomaly_path.exists() else None
        _load_error = None
        _feature_importance_cache = _build_feature_importance_cache()
    except Exception as exc:
        _load_error = str(exc)
        _feature_importance_cache = None


def models_ready() -> bool:
    load_models()
    return _model_water_level is not None and _model_weather is not None and _model_rainfall is not None


def loaded_model_names() -> list[str]:
    load_models()
    names = []
    if _model_water_level is not None:
        names.append("deteksi_banjir_berdasarkan_waterLevel")
    if _model_weather is not None:
        names.append("deteksi_banjir_berdasarkan_cuaca")
    if _model_rainfall is not None:
        names.append("prediksi_curah_hujan")
    if _model_anomaly is not None:
        names.append("deteksi_anomali")
    return names


def model_load_error() -> str | None:
    load_models()
    return _load_error


def predict_banjir(data: BanjirInput) -> dict:
    if not models_ready():
        raise RuntimeError(model_load_error() or "ML models are not loaded")

    input_m1 = np.array([[data.curahHujan, data.tinggiAir, data.kelembapanTanah]])
    input_m2 = np.array([[
        data.suhuMin, data.suhuMax, data.suhuRataRata, data.kelembapanUdara,
        data.sunShine, data.kecepatanAngin, data.arahAngin, data.kecepatanRataRataAngin,
    ]])

    prob_m1 = float(_model_water_level.predict_proba(input_m1)[0][1])
    prob_m2 = float(_model_weather.predict_proba(input_m2)[0][1])
    pred_m1 = 1 if prob_m1 >= 0.5 else 0
    pred_m2 = 1 if prob_m2 >= 0.5 else 0
    rata_prob = float((prob_m1 + prob_m2) / 2)

    if pred_m1 == 1 and pred_m2 == 1:
        prediksi = "BENCANA"
    elif pred_m1 == 1 or pred_m2 == 1:
        prediksi = "WASPADA"
    else:
        prediksi = "NORMAL"

    return {
        "status": "success",
        "code": 200,
        "data": {
            "idSungai": data.idSungai,
            "analisis_ketinggian_air": "BANJIR" if pred_m1 == 1 else "AMAN",
            "analisis_cuaca": "BANJIR" if pred_m2 == 1 else "AMAN",
            "hasil_prediksi": prediksi,
            "probabilitas": round(rata_prob, 2),
            "probabilitas_ketinggian_air": round(prob_m1, 4),
            "probabilitas_cuaca": round(prob_m2, 4),
        },
        "message": "Prediksi banjir berhasil",
        "service": "python-ml-service",
    }


def predict_curah_hujan(data: CurahHujanInput) -> dict:
    if not models_ready():
        raise RuntimeError(model_load_error() or "ML models are not loaded")

    vector = weather_vector_from_api(
        suhu_min=data.suhuMin,
        suhu_max=data.suhuMax,
        suhu_rata_rata=data.suhuRataRata,
        kelembapan_udara=data.kelembapanUdara,
        sun_shine=data.sunShine,
        kecepatan_angin=data.kecepatanAngin,
        arah_angin=data.arahAngin,
        kecepatan_rata_rata_angin=data.kecepatanRataRataAngin,
    )
    prediction, kategori, _, confidence = predict_rainfall_mm(_model_rainfall, vector)

    return {
        "status": "success",
        "code": 200,
        "data": {
            "idNode": data.idNode,
            "estimasi_curah_hujan_mm": round(prediction, 2),
            "kategori_cuaca": kategori,
            "confidence": round(confidence, 4),
        },
        "message": "Prediksi curah hujan berhasil",
        "service": "python-ml-service",
    }


def detect_anomaly(data: SensorAnomalyInput) -> dict:
    if not models_ready():
        raise RuntimeError(model_load_error() or "ML models are not loaded")

    if _model_anomaly is not None:
        bundle = _model_anomaly
        features = bundle["features"]
        X = bundle["scaler"].transform([[
            data.sensor_value,
            data.timestamp_hour,
            data.rolling_mean_1h,
            data.z_score,
        ]])
        score = float(bundle["model"].score_samples(X)[0])
        is_anom = score < -0.1
        anomaly_score = round(-score, 4)
    else:
        deviation = abs(data.sensor_value - data.rolling_mean_1h)
        threshold = max(1.0, abs(data.rolling_mean_1h) * 0.35)
        is_anom = abs(data.z_score) >= 2.0 or deviation >= threshold
        anomaly_score = round(max(abs(data.z_score), deviation / max(threshold, 0.01)), 4)
        score = -anomaly_score

    if score < -0.3 or anomaly_score >= 3:
        severity = "Kritis"
    elif is_anom:
        severity = "Peringatan"
    else:
        severity = "Normal"

    return {
        "status": "success",
        "code": 200,
        "data": {
            "is_anomaly": is_anom,
            "anomaly_score": anomaly_score,
            "severity": severity,
            "timestamp_hour": data.timestamp_hour,
        },
        "message": "Deteksi anomali berhasil",
        "service": "python-ml-service",
    }


def _tree_feature_importance(model, feature_names: list[str]) -> dict[str, float]:
    if not hasattr(model, "feature_importances_"):
        return {name: 0.0 for name in feature_names}

    values = model.feature_importances_.tolist()
    return {
        name: round(float(value), 4)
        for name, value in zip(feature_names, values)
    }


def feature_importance() -> dict:
    if not models_ready():
        raise RuntimeError(model_load_error() or "ML models are not loaded")

    if _feature_importance_cache is None:
        raise RuntimeError("Feature importance cache is not available")

    return {
        "status": "success",
        "code": 200,
        "data": _feature_importance_cache,
        "message": "Feature importance berhasil diambil",
        "service": "python-ml-service",
    }


def predict_batch(request: BatchPredictRequest) -> dict:
    results = []

    for item in request.items:
        if item.type == "traffic":
            payload = BanjirInput(**item.payload)
            results.append({"type": item.type, "result": predict_banjir(payload)["data"]})
        elif item.type == "air-quality":
            payload = CurahHujanInput(**item.payload)
            results.append({"type": item.type, "result": predict_curah_hujan(payload)["data"]})
        else:
            payload = SensorAnomalyInput(**item.payload)
            results.append({"type": item.type, "result": detect_anomaly(payload)["data"]})

    return {
        "status": "success",
        "code": 200,
        "data": {"predictions": results, "count": len(results)},
        "message": "Batch prediction berhasil",
        "service": "python-ml-service",
    }
