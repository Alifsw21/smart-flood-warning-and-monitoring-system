#!/usr/bin/env python3
"""RabbitMQ end-to-end checks for spec §4.5 / S6 (city.events topic exchange)."""

from __future__ import annotations

import json
import os
import sys
import time

import mysql.connector
import pika

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from rabbitmq_topology import (
    ROUTING_AIR_NEW,
    ROUTING_TRAFFIC_NEW,
    publish_event,
    rabbitmq_parameters,
    setup_events_topology,
)

MYSQL_HOST = os.environ.get("MYSQL_HOST", "127.0.0.1")
MYSQL_PORT = int(os.environ.get("MYSQL_PORT", "3307"))
MYSQL_DATABASE = os.environ.get("MYSQL_DATABASE", "kelompok2")
ANALYTICS_DB_USER = os.environ.get("ANALYTICS_DB_USER", "analytics")
ANALYTICS_DB_PASSWORD = os.environ.get("ANALYTICS_DB_PASSWORD", "AnalyticSecret")
RIVER_DB_USER = os.environ.get("RIVER_DB_USER", "river")
RIVER_DB_PASSWORD = os.environ.get("RIVER_DB_PASSWORD", "RiverSecret")

AIR_PAYLOAD = {
    "idNode": 1,
    "tinggiAir": 1.2,
    "kelembapanTanah": 55,
    "suhuMin": 24,
    "suhuMax": 31,
    "suhuRataRata": 27,
    "kelembapanUdara": 70,
    "sunShine": 6,
    "kecepatanAngin": 9,
    "arahAngin": 90,
    "kecepatanRataRataAngin": 7,
}

HTTP_INGESTED_PAYLOAD = {
    **AIR_PAYLOAD,
    "event": "sensor_data_ingested",
    "id": 999999,
    "tinggiAir": 7.7,
}

# High flood signal payload to trigger WASPADA/BENCANA path and anomaly.alert
BANJIR_PAYLOAD = {
    "idSungai": 1,
    "curahHujan": 80,
    "tinggiAir": 4.5,
    "kelembapanTanah": 95,
    "suhuMin": 25,
    "suhuMax": 32,
    "suhuRataRata": 28,
    "kelembapanUdara": 99,
    "sunShine": 0,
    "kecepatanAngin": 20,
    "arahAngin": 180,
    "kecepatanRataRataAngin": 15,
}


def analytics_conn():
    return mysql.connector.connect(
        host=MYSQL_HOST,
        port=MYSQL_PORT,
        user=ANALYTICS_DB_USER,
        password=ANALYTICS_DB_PASSWORD,
        database=MYSQL_DATABASE,
    )


def river_conn():
    return mysql.connector.connect(
        host=MYSQL_HOST,
        port=MYSQL_PORT,
        user=RIVER_DB_USER,
        password=RIVER_DB_PASSWORD,
        database=MYSQL_DATABASE,
    )


def count_analytics_alerts(conn) -> int:
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM analytics_peringatan")
    total = cursor.fetchone()[0]
    cursor.close()
    return int(total)


def count_sensor_readings(conn) -> int:
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM river_sensorReading")
    total = cursor.fetchone()[0]
    cursor.close()
    return int(total)


def latest_alert(conn) -> tuple[str, float] | None:
    cursor = conn.cursor()
    cursor.execute(
        "SELECT tipePeringatan, nilaiProbabilitas FROM analytics_peringatan "
        "WHERE idSungai = 1 ORDER BY id DESC LIMIT 1"
    )
    row = cursor.fetchone()
    cursor.close()
    if not row:
        return None
    return str(row[0]), float(row[1])


def wait_for(predicate, timeout_sec: float = 30.0, interval: float = 1.0) -> bool:
    deadline = time.time() + timeout_sec
    while time.time() < deadline:
        if predicate():
            return True
        time.sleep(interval)
    return False


def main() -> int:
    failures = 0

    conn = pika.BlockingConnection(rabbitmq_parameters())
    channel = conn.channel()
    setup_events_topology(channel)

    analytics_before = count_analytics_alerts(analytics_conn())
    readings_before = count_sensor_readings(river_conn())

    publish_event(channel, ROUTING_AIR_NEW, AIR_PAYLOAD)
    publish_event(channel, ROUTING_TRAFFIC_NEW, BANJIR_PAYLOAD)

    if wait_for(lambda: count_sensor_readings(river_conn()) > readings_before):
        print("PASS air.new consumed and river_sensorReading inserted")
    else:
        print("FAIL air.new consumer did not insert river_sensorReading")
        failures += 1

    if wait_for(lambda: count_analytics_alerts(analytics_conn()) > analytics_before):
        alert = latest_alert(analytics_conn())
        if alert and alert[0] in {"waspada", "bencana", "normal"}:
            print(f"PASS traffic.new -> anomaly.alert persisted ({alert[0]})")
        else:
            print("FAIL anomaly alert row has unexpected tipePeringatan")
            failures += 1
    else:
        print("FAIL traffic.new -> anomaly.alert not persisted in analytics_peringatan")
        failures += 1

    ingested_before = count_sensor_readings(river_conn())
    publish_event(channel, ROUTING_AIR_NEW, HTTP_INGESTED_PAYLOAD)
    time.sleep(2)
    ingested_after = count_sensor_readings(river_conn())
    if ingested_after == ingested_before:
        print("PASS air.new skips duplicate insert for php-river ingested events")
    else:
        print("FAIL air.new inserted duplicate row for php-river ingested event")
        failures += 1

    conn.close()

    if failures:
        print(f"RabbitMQ E2E: {failures} failure(s)")
        return 1

    print("PASS RabbitMQ E2E (city.events air.new + traffic.new + anomaly.alert)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
