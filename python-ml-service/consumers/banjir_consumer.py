from pydantic import BaseModel
import pika
import json
import redis
import joblib
import numpy as np

try:
    redis_client = redis.Redis(host='localhost', port=6700, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

model1 = joblib.load("models/deteksi_banjir_berdasarkan_waterLevel.pkl")
model2 = joblib.load("models/deteksi_banjir_berdasarkan_cuaca.pkl")

# 3. Skema Validasi Pydantic
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

connection = pika.BlockingConnection(pika.ConnectionParameters(host='localhost'))
channel = connection.channel()
channel.queue_declare(queue='banjir_queue')

print("Consumer deteksi banjir stand-by")

def callback(ch, method, properties, body):
    try:
        raw_data = json.loads(body)
        data = SensorPrediksiBanjir(**raw_data)

        input_m1 = np.array([[data.Rainfall_mm, data.WaterLevel_m, data.SoilMoisture_pct]])
        pred_m1 = model1.predict(input_m1)[0]

        input_m2 = np.array([[data.Tn, data.Tx, data.Tavg, data.RH_avg, data.ss, data.ff_x, data.ddd_x, data.ff_avg]])
        pred_m2 = model2.predict(input_m2)[0]

        if pred_m1 == 1 and pred_m2 == 1:
            prediksi = "BAHAYA"
        elif pred_m1 == 1 or pred_m2 == 1:
            prediksi = "WASPADA"
        else:
            prediksi = "NORMAL"

        hasil_prediksi = {
            "status": "success",
            "analisis_ketinggian_air": "BANJIR" if pred_m1 == 1 else "AMAN",
            "analisis_cuaca": "BANJIR" if pred_m2 == 1 else "AMAN",
            "hasil_prediksi": prediksi
        }

        if redis_client:
            redis_client.set("status_banjir_terakhir", json.dumps(hasil_prediksi))
            print(f"Berhasil memproses data sensor. Hasil prediksi: {prediksi}")
        else:
            print("Gagal menyimpan, koneksi redis terputus")

    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")

channel.basic_consume(queue='banjir_queue', on_message_callback=callback, auto_ack=True)
channel.start_consuming()