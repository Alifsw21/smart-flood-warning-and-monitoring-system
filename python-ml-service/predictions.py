from pathlib import Path

import joblib
import numpy as np
from pydantic import BaseModel, Field

MODELS_DIR = Path(__file__).resolve().parent / "models"

_model_water_level = None
_model_weather = None
_model_rainfall = None
_load_error = None


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


def load_models():
    global _model_water_level, _model_weather, _model_rainfall, _load_error

    if _model_water_level is not None:
        return

    try:
        _model_water_level = joblib.load(MODELS_DIR / "deteksi_banjir_berdasarkan_waterLevel.pkl")
        _model_weather = joblib.load(MODELS_DIR / "deteksi_banjir_berdasarkan_cuaca.pkl")
        _model_rainfall = joblib.load(MODELS_DIR / "prediksi_curah_hujan.pkl")
        _load_error = None
    except Exception as exc:
        _load_error = str(exc)


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

    input_features = np.array([[
        data.suhuMin, data.suhuMax, data.suhuRataRata, data.kelembapanUdara,
        data.sunShine, data.kecepatanAngin, data.arahAngin, data.kecepatanRataRataAngin,
    ]])
    prediction = max(0.0, float(_model_rainfall.predict(input_features)[0]))

    if prediction <= 5:
        kategori = "Cerah/Berawan"
    elif prediction <= 20:
        kategori = "Hujan Ringan"
    elif prediction <= 50:
        kategori = "Hujan Sedang"
    else:
        kategori = "Hujan Lebat"

    return {
        "status": "success",
        "code": 200,
        "data": {
            "idNode": data.idNode,
            "estimasi_curah_hujan_mm": round(prediction, 2),
            "kategori_cuaca": kategori,
        },
        "message": "Prediksi curah hujan berhasil",
        "service": "python-ml-service",
    }
