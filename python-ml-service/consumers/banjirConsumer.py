import json
import os
import sys

from dotenv import load_dotenv
import pika
import redis

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from consumers.common import decode_message, normalize_banjir_payload
from predictions import BanjirInput, load_models, predict_banjir
from rabbitmq_topology import (
    ROUTING_ANOMALY_ALERT,
    ROUTING_TRAFFIC_NEW,
    publish_event,
    rabbitmq_parameters,
    setup_events_topology,
)

load_dotenv()

REDIS_HOST = os.environ.get("REDIS_HOST", "localhost")
REDIS_PORT = int(os.environ.get("REDIS_PORT", "6379"))

try:
    redis_client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

load_models()

connection = pika.BlockingConnection(rabbitmq_parameters())
channel = connection.channel()
setup_events_topology(channel)

print(f"Consumer deteksi banjir stand-by on {ROUTING_TRAFFIC_NEW} (exchange city.events)")


def callback(ch, method, properties, body):
    try:
        raw_data = decode_message(body)
        data = BanjirInput(**normalize_banjir_payload(raw_data))
        result = predict_banjir(data)
        prediction = result["data"]

        prediksi = prediction["hasil_prediksi"]
        rata_prob = prediction["probabilitas"]

        if redis_client:
            redis_client.set("test_data_iot_di_ml", body.decode())
            redis_client.set("status_banjir_terakhir", json.dumps(prediction))

        alert_payload = {
            "event": "anomaly.alert",
            "idSungai": data.idSungai,
            "tipePeringatan": prediksi.lower(),
            "nilaiProbabilitas": rata_prob,
            "tinggiAir": data.tinggiAir,
            "hasil_prediksi": prediksi,
            "probabilitas": rata_prob,
            "analisis_ketinggian_air": prediction["analisis_ketinggian_air"],
            "analisis_cuaca": prediction["analisis_cuaca"],
        }
        publish_event(channel, ROUTING_ANOMALY_ALERT, alert_payload)

        print(
            f"Berhasil memproses data. Status: {prediksi} "
            f"(Probabilitas: {round(rata_prob * 100, 1)}%) — alert published"
        )
    except Exception as e:
        print(f"Gagal memproses pesan: {str(e)}")


channel.basic_consume(
    queue=ROUTING_TRAFFIC_NEW,
    on_message_callback=callback,
    auto_ack=True,
)
channel.start_consuming()
