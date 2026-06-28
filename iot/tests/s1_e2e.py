#!/usr/bin/env python3
"""S1 E2E: MQTT → Node-RED → Gateway → php-river → RabbitMQ → ML consumer."""

from __future__ import annotations

import json
import os
import sys
import time

import mysql.connector
import paho.mqtt.client as mqtt
import redis

MQTT_HOST = os.environ.get("MQTT_HOST", "127.0.0.1")
MQTT_PORT = int(os.environ.get("MQTT_PORT", "1883"))
MQTT_TOPIC_PREFIX = os.environ.get("MQTT_TOPIC_PREFIX", "kelompok2/sensors").rstrip("/")

MYSQL_HOST = os.environ.get("MYSQL_HOST", "127.0.0.1")
MYSQL_PORT = int(os.environ.get("MYSQL_PORT", "3307"))
MYSQL_DATABASE = os.environ.get("MYSQL_DATABASE", "kelompok2")
RIVER_DB_USER = os.environ.get("RIVER_DB_USER", "river")
RIVER_DB_PASSWORD = os.environ.get("RIVER_DB_PASSWORD", "RiverSecret")

REDIS_HOST = os.environ.get("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.environ.get("REDIS_PORT", "6379"))

TIMEOUT_SEC = float(os.environ.get("S1_E2E_TIMEOUT", "45"))


def river_conn():
    return mysql.connector.connect(
        host=MYSQL_HOST,
        port=MYSQL_PORT,
        user=RIVER_DB_USER,
        password=RIVER_DB_PASSWORD,
        database=MYSQL_DATABASE,
    )


def redis_client():
    client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    client.ping()
    return client


def max_reading_id() -> int:
    conn = river_conn()
    cursor = conn.cursor()
    cursor.execute("SELECT COALESCE(MAX(id), 0) FROM river_sensorReading")
    value = int(cursor.fetchone()[0])
    cursor.close()
    conn.close()
    return value


def latest_reading_after(min_id: int) -> tuple[int, float] | None:
    conn = river_conn()
    cursor = conn.cursor()
    cursor.execute(
        "SELECT id, tinggiAir FROM river_sensorReading WHERE id > %s ORDER BY id DESC LIMIT 1",
        (min_id,),
    )
    row = cursor.fetchone()
    cursor.close()
    conn.close()
    if not row:
        return None
    return int(row[0]), float(row[1])


def redis_ingested_tinggi_air() -> float | None:
    client = redis_client()
    raw = client.get("test_data_iot_di_ml")
    if not raw:
        return None
    payload = json.loads(raw)
    if payload.get("event") != "sensor_data_ingested":
        return None
    return float(payload.get("tinggiAir"))


def publish_pair(tinggi_air: float) -> None:
    sungai = {
        "idNode": 1,
        "tinggiAir": tinggi_air,
        "kelembapanTanah": 48.5,
    }
    cuaca = {
        "idNode": 2,
        "curahHujan": 11.2,
        "suhuMin": 26.0,
        "suhuMax": 31.0,
        "suhuRataRata": 28.5,
        "kelembapanUdara": 74.0,
        "sunShine": 5.5,
        "kecepatanAngin": 9.0,
        "arahAngin": 180.0,
        "kecepatanRataRataAngin": 7.0,
    }

    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.connect(MQTT_HOST, MQTT_PORT, 60)
    client.loop_start()
    try:
        client.publish(
            f"{MQTT_TOPIC_PREFIX}/sungai",
            json.dumps(sungai),
            qos=1,
            retain=False,
        )
        time.sleep(0.2)
        client.publish(
            f"{MQTT_TOPIC_PREFIX}/cuaca",
            json.dumps(cuaca),
            qos=1,
            retain=False,
        )
        time.sleep(0.5)
    finally:
        client.loop_stop()
        client.disconnect()


def wait_for(predicate, timeout_sec: float = TIMEOUT_SEC, interval: float = 1.0) -> bool:
    deadline = time.time() + timeout_sec
    while time.time() < deadline:
        if predicate():
            return True
        time.sleep(interval)
    return False


def main() -> int:
    failures = 0
    tinggi_air = round(2.0 + (time.time() % 1000) / 100.0, 2)

    print(
        f"S1 E2E (MQTT {MQTT_HOST}:{MQTT_PORT} → Node-RED → Gateway → php-river → ML) "
        f"tinggiAir={tinggi_air}"
    )

    # Allow Node-RED OAuth bootstrap (inject onceDelay=2s) before first MQTT pair.
    time.sleep(3)

    before_id = max_reading_id()
    publish_pair(tinggi_air)

    def db_has_new_reading() -> bool:
        latest = latest_reading_after(before_id)
        return latest is not None and abs(latest[1] - tinggi_air) < 0.05

    if wait_for(db_has_new_reading):
        print("PASS php-river persisted merged reading from /iot/traffic")
    else:
        print("FAIL php-river did not persist reading (Node-RED → Gateway chain)")
        failures += 1

    def redis_has_ingested_reading() -> bool:
        value = redis_ingested_tinggi_air()
        return value is not None and abs(value - tinggi_air) < 0.05

    if wait_for(redis_has_ingested_reading):
        print("PASS ML banjir consumer processed traffic.new (Redis marker)")
    else:
        print("FAIL ML consumer did not set test_data_iot_di_ml with expected tinggiAir")
        failures += 1

    if failures:
        print(f"S1 E2E: {failures} failure(s)")
        return 1

    print("PASS S1 E2E (MQTT → Node-RED → Gateway → PHP DB → RabbitMQ → ML)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
