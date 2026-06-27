from datetime import datetime, timezone

from fastapi import FastAPI, HTTPException
from contextlib import asynccontextmanager
from dotenv import load_dotenv
import paho.mqtt.client as mqtt
import threading
import redis
import json
import pika
import os

from predictions import (
    BanjirInput,
    BatchPredictRequest,
    CurahHujanInput,
    SensorAnomalyInput,
    detect_anomaly,
    feature_importance,
    load_models,
    loaded_model_names,
    model_load_error,
    predict_banjir,
    predict_batch,
    predict_curah_hujan,
)
from rabbitmq_topology import (
    ROUTING_AIR_NEW,
    ROUTING_TRAFFIC_NEW,
    publish_event,
    rabbitmq_credentials,
    setup_events_topology,
)

load_dotenv()

RABBITMQ_HOST = os.environ.get("RABBITMQ_HOST", "127.0.0.1")
RABBITMQ_PORT = int(os.environ.get("RABBITMQ_PORT", "5672"))
RABBITMQ_USER = os.environ.get("RABBITMQ_USER", "guest")
RABBITMQ_PASSWORD = os.environ.get("RABBITMQ_PASSWORD", "guest")
RABBITMQ_VHOST = os.environ.get("RABBITMQ_VHOST", "/")
REDIS_HOST = os.environ.get("REDIS_HOST", "localhost")
REDIS_PORT = int(os.environ.get("REDIS_PORT", "6379"))
MQTT_HOST = os.environ.get("MQTT_HOST", "broker.hivemq.com")
MQTT_PORT = int(os.environ.get("MQTT_PORT", "1883"))
MQTT_TOPIC_PREFIX = os.environ.get("MQTT_TOPIC_PREFIX", "kelompok2/sensors").rstrip("/")

try:
    redis_client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

latest_data = {"Node1": None, "Node2": None}

def start_background_worker():
    rmq_conn = pika.BlockingConnection(pika.ConnectionParameters(
        host=RABBITMQ_HOST,
        port=RABBITMQ_PORT,
        virtual_host=RABBITMQ_VHOST,
        credentials=rabbitmq_credentials(),
    ))
    rmq_channel = rmq_conn.channel()
    setup_events_topology(rmq_channel)

    def on_connect(client, userdata, flags, rc):
        print(f"Terhubung ke HiveMQ dengan kode: {rc}")
        client.subscribe(f"{MQTT_TOPIC_PREFIX}/sungai")
        client.subscribe(f"{MQTT_TOPIC_PREFIX}/cuaca")

    def on_message(client, userdata, msg):
        global latest_data
        payload = json.loads(msg.payload.decode())

        if "sungai" in msg.topic:
            latest_data["Node1"] = payload
        elif "cuaca" in msg.topic:
            latest_data["Node2"] = payload
        
        if latest_data["Node1"] and latest_data["Node2"]:
            gabung = {**latest_data["Node1"], **latest_data["Node2"]}

            try:
                gabung["idNode"] = latest_data["Node1"].get("idNode", 1)
                banjir_payload = gabung.copy()
                banjir_payload['idSungai'] = gabung["idNode"]

                publish_event(rmq_channel, ROUTING_TRAFFIC_NEW, banjir_payload)
                publish_event(rmq_channel, ROUTING_AIR_NEW, gabung)

                print("Data gabungan berhasil dikirim ke antrean RabbitMQ")
                latest_data = {"Node1": None, "Node2": None}
            except Exception as e:
                print(f"Gagal mengirim ke antrean: {str(e)}")

    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION1)
    client.on_connect = on_connect
    client.on_message = on_message
    client.connect(MQTT_HOST, MQTT_PORT, 60)
    client.loop_forever()

@asynccontextmanager
async def lifespan(app: FastAPI):
    load_models()
    threading.Thread(target=start_background_worker, daemon=True).start()
    yield

app = FastAPI(
    title="Flood Warning and Monitoring",
    lifespan=lifespan,
    description="Service pendeteksi banjir berdasarkan cuaca, dan tinggi air.",
    version="1.0.0"
)

def fetch_redis_data(key: str, empty_msg: str):
    if not redis_client:
        raise HTTPException(status_code=500, detail="Terjadi kesalahan pada redis server")
    data = redis_client.get(key)
    if not data:
        return {"message": empty_msg}
    
    return json.loads(data) 

@app.get("/health")
async def health_check():
    models = loaded_model_names()
    return {
        "status": "ok" if models else "degraded",
        "service": "python-ml-service",
        "models": models,
        "redis_connected": redis_client is not None,
        "model_error": model_load_error(),
    }

@app.get("/api/sensor")
async def cek_data_iot_ke_ml():
    return fetch_redis_data(
        key="test_data_iot_di_ml",
        empty_msg="ML dan IoT belum berhasil terhubung"
    )

@app.get("/predict/realtime/banjir")
async def get_realtime():
    return fetch_redis_data("status_banjir_terakhir", "Belum ada data sensor banjir")
    
@app.get("/predict/realtime/curah-hujan")
async def get_realtime_hujan():
    return fetch_redis_data("estimasi_hujan_terakhir", "Belum ada data sensor curah hujan")


def _timestamp() -> str:
    return datetime.now(timezone.utc).isoformat()


@app.post("/predict/banjir")
async def post_predict_banjir(payload: BanjirInput):
    try:
        result = predict_banjir(payload)
        result["timestamp"] = _timestamp()
        return result
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/predict/curah-hujan")
async def post_predict_curah_hujan(payload: CurahHujanInput):
    try:
        result = predict_curah_hujan(payload)
        result["timestamp"] = _timestamp()
        return result
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/predict/traffic")
async def post_predict_traffic_alias(payload: BanjirInput):
    return await post_predict_banjir(payload)


@app.post("/predict/air-quality")
async def post_predict_air_quality_alias(payload: CurahHujanInput):
    return await post_predict_curah_hujan(payload)


@app.post("/detect/anomaly")
async def post_detect_anomaly(payload: SensorAnomalyInput):
    try:
        result = detect_anomaly(payload)
        result["timestamp"] = _timestamp()
        return result
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.get("/model/feature-importance")
async def get_feature_importance():
    try:
        result = feature_importance()
        result["timestamp"] = _timestamp()
        return result
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/predict/batch")
async def post_predict_batch(request: BatchPredictRequest):
    try:
        result = predict_batch(request)
        result["timestamp"] = _timestamp()
        return result
    except (RuntimeError, ValueError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
