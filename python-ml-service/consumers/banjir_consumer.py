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
    Rainfall_mm: float = 0.0
    WaterLevel_m: float = 0.0
    SoilMoisture_pct: float = 0.0
    Tn: float = 25.0 # Min temperature
    Tx: float = 32.0 # Max temperature
    Tavg: float = 28.0 # Avg temperature
    RH_avg: float = 77.0 # Avg humidity
    ss: float = 0.0 # duration of sunshine
    ff_x: float = 0.0 # max wind speed
    ddd_x: float = 0.0 # wind direction at maximum speed
    ff_avg: float = 0.0 # max wind speed

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
            redis_client.set("test_data_iot_di_ml", body.decode())
            redis_client.set("status_banjir_terakhir", json.dumps(hasil_prediksi))

        if db_conn is None or not db_conn.is_connected():
            db_conn = get_mysql_connection()

        if db_conn:
            cursor = db_conn.cursor()

            query_peringatan = """
                INSERT INTO analytics_peringatan (idSungai, tipePeringatan, nilaiProbabilitas)
                VALUES (%s, %s, %s)
            """
            values_peringatan = (data.idSungai, prediksi.lower(), round(rata_prob, 2))
            cursor.execute(query_peringatan, values_peringatan)
            db_conn.commit()

            if prediksi in ["WASPADA", "BENCANA"]:
                if data.WaterLevel_m >= 3.5:
                    status_riwayat = "tinggi"
                elif data.WaterLevel_m >= 2.0:
                    status_riwayat = "sedang"
                else:
                    status_riwayat = "ringan"
                
                query_riwayat = """
                    INSERT INTO user_riwayatBanjir (idSungai, tinggiAir, status)
                    VALUES (%s, %s, %s)
                """
                values_riwayat = (data.idSungai, data.WaterLevel_m, status_riwayat)
                cursor.execute(query_riwayat, values_riwayat)
                db_conn.commit()

            cursor.close()

            print(f"Berhasil memproses data. Status: {prediksi} (Probabilitas: {round(rata_prob * 100, 1)}%)")   
    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")

channel.basic_consume(queue='banjir_queue', on_message_callback=callback, auto_ack=True)
channel.start_consuming()