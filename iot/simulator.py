#!/usr/bin/env python3
"""Periodic MQTT publisher for sungai + cuaca sensors (spec §4.6B, S1 demo)."""

from __future__ import annotations

import json
import os
import random
import time
from datetime import datetime, timezone

import paho.mqtt.client as mqtt

MQTT_HOST = os.environ.get("MQTT_HOST", "localhost")
MQTT_PORT = int(os.environ.get("MQTT_PORT", "1883"))
MQTT_TOPIC_PREFIX = os.environ.get("MQTT_TOPIC_PREFIX", "kelompok2/sensors").rstrip("/")
MQTT_USER = os.environ.get("MQTT_USER", "")
MQTT_PASS = os.environ.get("MQTT_PASS", "")
INTERVAL_SEC = int(os.environ.get("SIMULATOR_INTERVAL_SEC", "30"))


def build_sungai_payload() -> dict:
    duration_us = random.randint(8000, 25000)
    distance_m = (duration_us * 0.034 / 2) / 100.0
    water_level_m = max(0.0, 5.0 - distance_m)
    soil_moisture = (random.randint(900, 3200) / 4095.0) * 100.0
    return {
        "idNode": 1,
        "tinggiAir": round(water_level_m, 2),
        "kelembapanTanah": round(soil_moisture, 1),
    }


def build_cuaca_payload() -> dict:
    t_avg = random.uniform(26.0, 32.0)
    rh_avg = random.uniform(60.0, 90.0)
    rainfall = (random.randint(0, 4095) / 4095.0) * 50.0
    return {
        "idNode": 2,
        "curahHujan": round(rainfall, 1),
        "suhuMin": round(t_avg - 2.0, 1),
        "suhuMax": round(t_avg + 3.0, 1),
        "suhuRataRata": round(t_avg, 1),
        "kelembapanUdara": round(rh_avg, 1),
        "sunShine": round(5.0 + random.uniform(-1.0, 1.0), 1),
        "kecepatanAngin": round(5.0 + random.uniform(-1.0, 1.0), 1),
        "arahAngin": round(193.5 + random.uniform(-20.0, 20.0), 1),
        "kecepatanRataRataAngin": round(2.3 + random.uniform(-1.0, 1.0), 1),
    }


def main() -> None:
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id="iot-simulator")
    if MQTT_USER:
        client.username_pw_set(MQTT_USER, MQTT_PASS)
    client.connect(MQTT_HOST, MQTT_PORT, 60)
    client.loop_start()

    print(
        f"IoT simulator publishing to {MQTT_TOPIC_PREFIX}/{{sungai,cuaca}} "
        f"every {INTERVAL_SEC}s via {MQTT_HOST}:{MQTT_PORT}"
    )

    try:
        while True:
            sungai = build_sungai_payload()
            cuaca = build_cuaca_payload()
            now = datetime.now(timezone.utc).isoformat()

            client.publish(
                f"{MQTT_TOPIC_PREFIX}/sungai",
                json.dumps(sungai),
                qos=1,
            )
            client.publish(
                f"{MQTT_TOPIC_PREFIX}/cuaca",
                json.dumps(cuaca),
                qos=1,
            )
            print(f"[{now}] sungai={sungai} cuaca_keys={list(cuaca.keys())}")
            time.sleep(INTERVAL_SEC)
    except KeyboardInterrupt:
        print("Simulator stopped.")
    finally:
        client.loop_stop()
        client.disconnect()


if __name__ == "__main__":
    main()
