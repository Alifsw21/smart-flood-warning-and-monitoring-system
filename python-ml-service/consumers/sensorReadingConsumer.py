import os
import sys

from dotenv import load_dotenv
import mysql.connector
import pika
import redis

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from consumers.common import decode_message, is_http_ingested_event, normalize_sensor_payload
from predictions import CurahHujanInput, load_models, predict_curah_hujan
from rabbitmq_topology import (
    ROUTING_AIR_NEW,
    rabbitmq_parameters,
    setup_events_topology,
)

load_dotenv()

MYSQL_HOST = os.environ.get("MYSQL_HOST", "localhost")
RIVER_DB_USER = os.environ.get("RIVER_DB_USER", "river")
RIVER_DB_PASSWORD = os.environ.get("RIVER_DB_PASSWORD", "RiverSecret")
MYSQL_DATABASE = os.environ.get("MYSQL_DATABASE", "kelompok2")
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
            database=MYSQL_DATABASE,
            autocommit=True,
        )
    except Exception as e:
        print(f"Gagal koneksi ke database: {str(e)}")
        return None


load_models()

connection = pika.BlockingConnection(rabbitmq_parameters())
channel = connection.channel()
setup_events_topology(channel)

print(f"consumer sensor reading stand-by on {ROUTING_AIR_NEW} (exchange city.events)")

db_conn = get_mysql_connection()


def callback(ch, method, properties, body):
    global db_conn
    try:
        raw_data = decode_message(body)
        data = CurahHujanInput(**normalize_sensor_payload(raw_data))
        result = predict_curah_hujan(data)
        prediction = result["data"]
        rainfall = prediction["estimasi_curah_hujan_mm"]
        kategori = prediction["kategori_cuaca"]

        if redis_client:
            import json

            redis_client.set("estimasi_hujan_terakhir", json.dumps(prediction))
        else:
            print("Gagal menyimpan, koneksi redis terputus")

        already_persisted = is_http_ingested_event(raw_data)

        if db_conn is None or not db_conn.is_connected():
            db_conn = get_mysql_connection()

        if db_conn and not already_persisted:
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
                round(rainfall, 2),
                data.suhuRataRata,
                data.kelembapanUdara,
                data.kecepatanRataRataAngin,
                str(data.arahAngin),
            )
            cursor.execute(query, values)
            cursor.close()

            print(
                f"Berhasil memproses & mencatat data. "
                f"Hasil Prediksi Hujan: {round(rainfall, 2)} mm ({kategori})"
            )
        elif already_persisted:
            print(
                f"Berhasil memproses prediksi (data sudah disimpan php-river). "
                f"Hasil Prediksi Hujan: {round(rainfall, 2)} mm ({kategori})"
            )
    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")


channel.basic_consume(
    queue=ROUTING_AIR_NEW,
    on_message_callback=callback,
    auto_ack=True,
)
channel.start_consuming()
