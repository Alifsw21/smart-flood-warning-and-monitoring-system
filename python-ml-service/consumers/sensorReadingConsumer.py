from pydantic import BaseModel
from dotenv import load_dotenv
import pika
import json
import redis
import numpy as np
import mysql.connector
import joblib
import os

load_dotenv()

MYSQL_HOST = os.environ.get("MYSQL_HOST", "localhost")
RIVER_DB_USER = os.environ.get("RIVER_DB_USER", "river")
RIVER_DB_PASSWORD = os.environ.get("RIVER_DB_PASSWORD", "RiverSecret")
MYSQL_DATABASE = os.environ.get("MYSQL_DATABASE", "kelompok2")
RABBITMQ_HOST = os.environ.get("RABBITMQ_HOST", "127.0.0.1")
RABBITMQ_PORT = int(os.environ.get("RABBITMQ_PORT", "5672"))
RABBITMQ_USER = os.environ.get("RABBITMQ_USER", "guest")
RABBITMQ_PASSWORD = os.environ.get("RABBITMQ_PASSWORD", "guest")
RABBITMQ_VHOST = os.environ.get("RABBITMQ_VHOST", "/")
REDIS_HOST = os.environ.get("REDIS_HOST", "localhost")
REDIS_PORT = int(os.environ.get("REDIS_PORT", "6379"))

try:
    redis_client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

def get_mysql_connection():
    try:
        return mysql.connector.connect(
            host=MYSQL_HOST,
            user=RIVER_DB_USER,
            password=RIVER_DB_PASSWORD,
            database=MYSQL_DATABASE
        )
    except Exception as e:
        print(f"Gagal koneksi ke database: {str(e)}")
        return None

model = joblib.load(os.path.join(os.path.dirname(__file__), '..', 'models', 'prediksi_curah_hujan.pkl'))

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

kredensial = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASSWORD)

parameter = pika.ConnectionParameters(
    host=RABBITMQ_HOST,
    port=RABBITMQ_PORT,
    virtual_host=RABBITMQ_VHOST,
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
