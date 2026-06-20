from pydantic import BaseModel
import pika
import json
import redis
import joblib
import mysql.connector
import numpy as np

try:
    redis_client = redis.Redis(host='localhost', port=6379, decode_responses=True)
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
    
model1 = joblib.load("../models/deteksi_banjir_berdasarkan_waterLevel.pkl")
model2 = joblib.load("../models/deteksi_banjir_berdasarkan_cuaca.pkl")

class SensorPrediksiBanjir(BaseModel):
    idSungai: int
    curahHujan: float = 0.0 # Rainfall_mm
    tinggiAir: float = 0.0 # WaterLevel_m
    kelembapanTanah: float = 0.0 # SoilMoisture_pct 
    suhuMin: float = 25.0 # Tn
    suhuMax: float = 32.0 # Tx
    suhuRataRata: float = 28.0 # Tavg
    kelembapanUdara: float = 77.0 # RH_avg
    sunShine: float = 0.0 # ss
    kecepatanAngin: float = 0.0 # ff_x
    arahAngin: float = 0.0 # ddd_x
    kecepatanRataRataAngin: float = 0.0 # ff_avg

kredensial = pika.PlainCredentials('guest', 'guest')

parameter = pika.ConnectionParameters(
    host='127.0.0.1',
    port=5672,
    virtual_host='/',
    credentials=kredensial
)

connection = pika.BlockingConnection(parameter)

channel = connection.channel()
channel.queue_declare(queue='banjir_queue')

print("Consumer deteksi banjir stand-by")

db_conn = get_mysql_connection()

def callback(ch, method, properties, body):
    global db_conn
    try:
        raw_data = json.loads(body)
        data = SensorPrediksiBanjir(**raw_data)

        input_m1 = np.array([[data.curahHujan, data.tinggiAir, data.kelembapanTanah]])
        input_m2 = np.array([[data.suhuMin, data.suhuMax, data.suhuRataRata, data.kelembapanUdara, data.sunShine, data.kecepatanAngin, data.arahAngin, data.kecepatanRataRataAngin]])

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
                if data.tinggiAir >= 3.5:
                    status_riwayat = "tinggi"
                elif data.tinggiAir >= 2.0:
                    status_riwayat = "sedang"
                else:
                    status_riwayat = "ringan"
                
                query_riwayat = """
                    INSERT INTO user_riwayatBanjir (idSungai, tinggiAir, status)
                    VALUES (%s, %s, %s)
                """
                values_riwayat = (data.idSungai, data.tinggiAir, status_riwayat)
                cursor.execute(query_riwayat, values_riwayat)
                db_conn.commit()

            cursor.close()

            print(f"Berhasil memproses data. Status: {prediksi} (Probabilitas: {round(rata_prob * 100, 1)}%)")   
    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")

channel.basic_consume(queue='banjir_queue', on_message_callback=callback, auto_ack=True)
channel.start_consuming()