from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List
import numpy as np
import joblib
import redis
import json

app = FastAPI(
    title="Flood Warning and Monitoring",
    description="Service pendeteksi banjir berdasarkan cuaca, dan tinggi air.",
    version="1.0.0"
)

try:
    redis_client = redis.Redis(host='localhost', port=6700, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

model_waterlevel = joblib.load("models/deteksi_banjir_berdasarkan_waterLevel.pkl")
model_cuaca = joblib.load("models/deteksi_banjir_berdasarkan_cuaca.pkl")
model_hujan = joblib.load("models/prediksi_curah_hujan.pkl")

class SensorPrediksiBanjir(BaseModel):
    Rainfall_mm: float
    WaterLevel_m: float
    SoilMoisture_pct: float
    Tn: float
    Tx: float
    Tavg: float
    RH_avg: float
    ss: float
    ff_x: float
    ddd_x: float
    ff_avg: float

class SensorCurahHujan(BaseModel):
    Tn: float
    Tx: float
    Tavg: float
    RH_avg: float
    ss: float
    ff_x: float
    ddd_x: float
    ff_avg: float

@app.get("/health")
async def health_check():
    return {
        "status": "ok", 
        "service": "ready", 
        "redis_connected": redis_client is not None
    }

@app.post("/predict/banjir")
async def predict_water_level(data: SensorPrediksiBanjir):
    try:
        if redis_client:
            redis_client.set("data_sensor_terakhir", json.dumps(data.model_dump()))

        input_m1 = np.array([[data.Rainfall_mm, data.WaterLevel_m, data.SoilMoisture_pct]])
        pred_m1 = model_waterlevel.predict(input_m1)[0]

        input_m2 = np.array([[data.Tn, data.Tx, data.Tavg, data.RH_avg, data.ss, data.ff_x, data.ddd_x, data.ff_avg]])
        pred_m2 = model_cuaca.predict(input_m2)[0]

        if pred_m1 == 1 and pred_m2 == 1:
            prediksi = "BAHAYA"
        elif pred_m1 == 1 or pred_m2 == 1:
            prediksi = "WASPADA"
        else:
            prediksi = "NORMAL"

        return {
            "status": "success",
            "analisis_ketinggian_air": "BANJIR" if pred_m1 == 1 else "AMAN",
            "analisis_cuaca": "BANJIR" if pred_m2 == 1 else "AMAN",
            "hasil_prediksi": prediksi
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/predict/realtime")
async def get_realtime():
    try:
        if not redis_client:
            raise HTTPException(status_code=500, detail="Terjadi kesalahan pada redis server")
        
        data = redis_client.get("data_sensor_terakhir")
        if not data:
            return {"message": "Belum ada data sensor masuk dari perangkat IoT"}
        
        return json.loads(data)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/predict/curah-hujan")
async def predict_curah_hujan(data: SensorCurahHujan):
    try:
        input = np.array([[data.Tn, data.Tx, data.Tavg, data.RH_avg, data.ss, data.ff_x, data.ddd_x, data.ff_avg]])
    
        prediction = float(model_hujan.predict(input)[0])

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
            "estimasi_curah_hujan_mm": round(prediction, 2),
            "kategori_cuaca": kategori
        }
    
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))