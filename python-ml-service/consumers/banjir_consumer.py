from pydantic import BaseModel
import pika
import json
import redis
import joblib
import mysql.connector
import numpy as np

try:
    redis_client = redis.Redis(host='localhost', port=6700, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

def get_mysql_connection():
    try:
        return mysql.connector.connect(
            host="localhost",
            user="analytics",
            password="AnalyticSecret",
            database="kelompok2"
        )
    except Exception as e:
        print(f"Gagal koneksi ke database: {str(e)}")
        return None
    
model1 = joblib.load("models/deteksi_banjir_berdasarkan_waterLevel.pkl")
model2 = joblib.load("models/deteksi_banjir_berdasarkan_cuaca.pkl")

class SensorPrediksiBanjir(BaseModel):
    idSungai: int
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

db_conn = get_mysql_connection()

def callback(ch, method, properties, body):
    global db_conn
    try:
        raw_data = json.loads(body)
        data = SensorPrediksiBanjir(**raw_data)

        input_m1 = np.array([[data.Rainfall_mm, data.WaterLevel_m, data.SoilMoisture_pct]])
        input_m2 = np.array([[data.Tn, data.Tx, data.Tavg, data.RH_avg, data.ss, data.ff_x, data.ddd_x, data.ff_avg]])

        prob_m1 = model1.predict_proba(input_m1)[0][1]
        prob_m2 = model2.predict_proba(input_m2)[0][1]

        pred_m1 = 1 if prob_m1 >= 0.5 else 0
        pred_m2 = 1 if prob_m2 >= 0.5 else 0

        rata_prob = float((prob_m1 + prob_m2) / 2)
        
        if pred_m1 == 1 and pred_m2 == 1:
            prediksi = "BENCANA"
        elif pred_m1 == 1 or pred_m2 == 1:
            prediksi = "WASPADA"
        else:
            prediksi = "NORMAL"

        hasil_prediksi = {
            "status": "success",
            "analisis_ketinggian_air": "BANJIR" if pred_m1 == 1 else "AMAN",
            "analisis_cuaca": "BANJIR" if pred_m2 == 1 else "AMAN",
            "hasil_prediksi": prediksi,
            "probabilitas": round(rata_prob, 2)
        }

        if redis_client:
            redis_client.set("status_banjir_terakhir", json.dumps(hasil_prediksi))

        if db_conn is None or not db_conn.is_connected():
            db_conn = get_mysql_connection()

        if db_conn:
            cursor = db_conn.cursor()

            query = """
                INSERT INTO analytics_peringatan (idSungai, tipePeringatan, nilaiProbabilitas)
                VALUES (%s, %s, %s)
            """
            values = (data.idSungai, prediksi.lower(), round(rata_prob, 2))

            cursor.execute(query, values)
            db_conn.commit()
            cursor.close()

            print(f"Berhasil memproses data. Status: {prediksi} (Probabilitas: {round(rata_prob * 100, 1)}%)")
            
    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")

channel.basic_consume(queue='banjir_queue', on_message_callback=callback, auto_ack=True)
channel.start_consuming()