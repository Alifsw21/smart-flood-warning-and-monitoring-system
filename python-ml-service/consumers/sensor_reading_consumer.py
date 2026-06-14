from pydantic import BaseModel
import pika
import json
import redis
import numpy as np
import mysql.connector
import joblib

try:
    redis_client = redis.Redis(host='localhost', port=6700, decode_responses=True)
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

model = joblib.load("models/prediksi_curah_hujan.pkl")

class SensorReading(BaseModel):
    idNode: int
    tinggiAir: float
    kelembapanTanah: float
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
channel.queue_declare(queue='sensor_queue')

print("consumer sensor reading stand-by")

db_conn = get_mysql_connection()
def callback(ch, method, properties, body):
    global db_conn
    try:
        raw_data = json.loads(body)
        data = SensorReading(**raw_data)

        input = np.array([[data.Tn, data.Tx, data.Tavg, data.RH_avg, data.ss, data.ff_x, data.ddd_x, data.ff_avg]])

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
                data.Tavg,
                data.RH_avg,
                data.ff_avg,
                str(data.ddd_x)
            )

            cursor.execute(query, values)
            db_conn.commit()
            cursor.close()

            print(f"Berhasil memproses & mencatat data. Hasil Prediksi Hujan: {round(prediction, 2)} mm ({kategori})")

    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")

channel.basic_consume(queue='sensor_queue', on_message_callback=callback, auto_ack=True)
channel.start_consuming()