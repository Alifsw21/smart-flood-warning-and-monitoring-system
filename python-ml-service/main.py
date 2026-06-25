from fastapi import FastAPI, HTTPException
from contextlib import asynccontextmanager
from dotenv import load_dotenv
import paho.mqtt.client as mqtt
import threading
import redis
import json
import pika
import os

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
    rmq_credentials = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASSWORD)
    rmq_conn = pika.BlockingConnection(pika.ConnectionParameters(
        host=RABBITMQ_HOST,
        port=RABBITMQ_PORT,
        virtual_host=RABBITMQ_VHOST,
        credentials=rmq_credentials
    ))
    rmq_channel = rmq_conn.channel()
    rmq_channel.queue_declare(queue='sensor_queue')
    rmq_channel.queue_declare(queue='banjir_queue')

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

                rmq_channel.basic_publish(exchange='', routing_key='banjir_queue', body=json.dumps(banjir_payload))
                rmq_channel.basic_publish(exchange='', routing_key='sensor_queue', body=json.dumps(gabung))

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
    return {
        "status": "ok", 
        "service": "ready", 
        "redis_connected": redis_client is not None
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
