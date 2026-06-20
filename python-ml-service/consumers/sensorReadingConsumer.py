from pydantic import BaseModel
import pika
import json
import redis
import numpy as np
import mysql.connector
import joblib

try:
    redis_client = redis.Redis(host='localhost', port=6379, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

def get_mysql_connection():
    try:
        return mysql.connector.connect(
            host="localhost",
            user="river",
            password="RiverSecret",
            database="kelompok2"
        )
    except Exception as e:
        print(f"Gagal koneksi ke database: {str(e)}")
        return None

model = joblib.load("../models/prediksi_curah_hujan.pkl")

class SensorReading(BaseModel):
    idNode: int
    tinggiAir: float # WaterLevel_m
    kelembapanTanah: float # SoilMoisture_pct
    suhuMin: float # Tn
    suhuMax: float # Tx
    suhuRataRata: float # Tavg
    kelembapanUdara: float # RH_avg
    sunShine: float # ss
    kecepatanAngin: float # ff_x
    arahAngin: float # ddd_x
    kecepatanRataRataAngin: float # ff_avg

kredensial = pika.PlainCredentials('guest', 'guest')

parameter = pika.ConnectionParameters(
    host='127.0.0.1',
    port=5672,
    virtual_host='/',
    credentials=kredensial
)

connection = pika.BlockingConnection(parameter)

channel = connection.channel()
channel.queue_declare(queue='sensor_queue')

print("consumer sensor reading stand-by")

db_conn = get_mysql_connection()
def callback(ch, method, properties, body):
    global db_conn
    try:
        raw_data = json.loads(body)
        data = SensorReading(**raw_data)

        input = np.array([[data.suhuMin, data.suhuMax, data.suhuRataRata, data.kelembapanUdara, data.sunShine, data.kecepatanAngin, data.arahAngin, data.kecepatanRataRataAngin]])

        prediction = float(model.predict(input)[0])
        prediction = max(0.0, prediction)

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
        else:
            print("Gagal menyimpan, koneksi redis terputus")

        if db_conn is None or not db_conn.is_connected():
            db_conn = get_mysql_connection()

        if db_conn:
            cursor = db_conn.cursor()

            query = """
                INSERT INTO river_sensorReading (
                    idNode, tinggiAir, kelembapanTanah, curahHujan, 
                    suhuRataRata, kelembapanUdara, kecepatanAngin, arahAngin
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """
            values = (
                data.idNode,
                data.tinggiAir,
                data.kelembapanTanah,
                round(prediction, 2),
                data.suhuRataRata,
                data.kelembapanUdara,
                data.kecepatanRataRataAngin,
                str(data.arahAngin)
            )

            cursor.execute(query, values)
            db_conn.commit()
            cursor.close()

            print(f"Berhasil memproses & mencatat data. Hasil Prediksi Hujan: {round(prediction, 2)} mm ({kategori})")

    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")

channel.basic_consume(queue='sensor_queue', on_message_callback=callback, auto_ack=True)
channel.start_consuming()