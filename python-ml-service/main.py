from fastapi import FastAPI, HTTPException
from contextlib import asynccontextmanager
import paho.mqtt.client as mqtt
import threading
import redis
import json
import pika

try:
    redis_client = redis.Redis(host='localhost', port=6700, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

latest_data = {"Node1": None, "Node2": None}

def start_background_worker():
    rmq_conn = pika.BlockingConnection(pika.ConnectionParameters(host='localhost'))
    rmq_channel = rmq_conn.channel()
    rmq_channel.queue_declare(queue='sensor_queue')
    rmq_channel.queue_declare(queue='banjir_queue')

    def on_connect(client, userdata, flags, rc):
        print(f"Terhubung ke HiveMQ dengan kode: {rc}")
        client.subscribe("kelompok2/sensors/sungai")
        client.subscribe("kelompok2/sensors/cuaca")

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
                banjir_payload = gabung.copy()
                banjir_payload['idSungai'] = gabung.get("idNode", 1)

                rmq_channel.basic_publish(exchange='', routing_key='banjir_queue', body=json.dumps(banjir_payload))
                rmq_channel.basic_publish(exchange='', routing_key='sensor_queue', body=json.dumps(gabung))

                print("Data gabungan berhasil dikirim ke antrean RabbitMQ")
                latest_data = {"Node1": None, "Node2": None}
            except Exception as e:
                print(f"Gagal mengirim ke antrean: {str(e)}")

    client = mqtt.Client()
    client.on_connect = on_connect
    client.on_message = on_message
    client.connect("broker.hivemq.com", 1883, 60)
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