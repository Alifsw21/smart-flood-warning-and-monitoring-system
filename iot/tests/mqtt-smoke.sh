#!/usr/bin/env bash
set -euo pipefail

MQTT_HOST="${MQTT_HOST:-localhost}"
MQTT_PORT="${MQTT_PORT:-1883}"
PREFIX="${MQTT_TOPIC_PREFIX:-kelompok2/sensors}"

echo "MQTT smoke: publish + subscribe on ${MQTT_HOST}:${MQTT_PORT} (${PREFIX}/*)"

if ! command -v python3 >/dev/null 2>&1; then
  echo "FAIL python3 required"
  exit 1
fi

python3 - <<PY
import json
import os
import sys
import time

import paho.mqtt.client as mqtt

host = os.environ.get("MQTT_HOST", "localhost")
port = int(os.environ.get("MQTT_PORT", "1883"))
prefix = os.environ.get("MQTT_TOPIC_PREFIX", "kelompok2/sensors").rstrip("/")

got = []

def on_message(_c, _u, msg):
    got.append(msg.topic)
    print(f"  received {msg.topic}")

sub = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id="mqtt-smoke-sub")
sub.on_message = on_message
try:
    sub.connect(host, port, 60)
except Exception as exc:
    print(f"FAIL cannot connect to MQTT broker at {host}:{port} ({exc})")
    print("Hint: run 'docker compose up -d mosquitto' and use MQTT_HOST=localhost")
    sys.exit(1)

sub.subscribe(f"{prefix}/#", qos=1)
sub.loop_start()
time.sleep(0.5)

pub = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id="mqtt-smoke-pub")
pub.connect(host, port, 60)
pub.loop_start()
pub.publish(
    f"{prefix}/sungai",
    json.dumps({"idNode": 1, "tinggiAir": 2.2, "kelembapanTanah": 50}),
    qos=1,
)
pub.publish(
    f"{prefix}/cuaca",
    json.dumps(
        {
            "idNode": 2,
            "curahHujan": 8,
            "suhuRataRata": 28,
            "kelembapanUdara": 70,
            "kecepatanAngin": 7,
        }
    ),
    qos=1,
)
time.sleep(2)
sub.loop_stop()
pub.loop_stop()

topics = {f"{prefix}/sungai", f"{prefix}/cuaca"}
if topics.issubset(set(got)):
    print("PASS MQTT broker delivers sungai + cuaca")
    sys.exit(0)

print(f"FAIL expected {topics}, got {set(got)}")
sys.exit(1)
PY
