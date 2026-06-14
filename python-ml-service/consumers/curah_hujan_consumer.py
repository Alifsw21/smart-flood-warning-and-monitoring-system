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

model = joblib.load("models/prediksi_curah_hujan.pkl")

class SensorCurahHujan(BaseModel):
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
channel.queue_declare(queue='curah_hujan_queue')

print("consumer deteksi curah hujan stand-by")

def callback(ch, method, properties, body):
    try:
        raw_data = json.loads(body)
        data = SensorCurahHujan(**raw_data)

        input = np.array([[data.Tn, data.Tx, data.Tavg, data.RH_avg, data.ss, data.ff_x, data.ddd_x, data.ff_avg]])

        prediction = float(model.predict(input)[0])

        if prediction <= 5:
            kategori = "Cerah/Berawan"
        elif prediction <= 20:
            kategori = "Hujan Ringan"
        elif prediction <= 50:
            kategori = "Hujan Sedang"
        else:
            kategori = "Hujan Lebat"

        hasil_prediksi = {
            "status": "success",
            "estimasi_curah_hujan_mm": round(prediction, 2),
            "kategori_cuaca": kategori 
        }

        if redis_client:
            redis_client.set("estimasi_hujan_terakhir", json.dumps(hasil_prediksi))
            print(f"Berhasil memproses curah hujan. Hasil: {prediction} mm ({kategori})")
        else:
            print("Gagal menyimpan, koneksi redis terputus")

    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")