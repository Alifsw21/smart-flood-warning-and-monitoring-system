"""Shared helpers for RabbitMQ consumer processes."""

from __future__ import annotations

import json
from typing import Any


def normalize_banjir_payload(raw: dict[str, Any]) -> dict[str, Any]:
    data = dict(raw)
    if "idSungai" not in data and "idNode" in data:
        data["idSungai"] = data["idNode"]
    data.setdefault("curahHujan", 0.0)
    data.setdefault("tinggiAir", 0.0)
    data.setdefault("kelembapanTanah", 0.0)
    data.setdefault("suhuMin", 25.0)
    data.setdefault("suhuMax", 32.0)
    data.setdefault("suhuRataRata", 28.0)
    data.setdefault("kelembapanUdara", 77.0)
    data.setdefault("sunShine", 0.0)
    data.setdefault("kecepatanAngin", 0.0)
    data.setdefault("arahAngin", 0.0)
    data.setdefault("kecepatanRataRataAngin", 0.0)
    return data


def normalize_sensor_payload(raw: dict[str, Any]) -> dict[str, Any]:
    data = dict(raw)
    if "idNode" not in data and "idSungai" in data:
        data["idNode"] = data["idSungai"]
    data.setdefault("tinggiAir", 0.0)
    data.setdefault("kelembapanTanah", 0.0)
    data.setdefault("suhuMin", 25.0)
    data.setdefault("suhuMax", 32.0)
    data.setdefault("suhuRataRata", 28.0)
    data.setdefault("kelembapanUdara", 77.0)
    data.setdefault("sunShine", 0.0)
    data.setdefault("kecepatanAngin", 0.0)
    data.setdefault("arahAngin", 0.0)
    data.setdefault("kecepatanRataRataAngin", 0.0)
    return data


def decode_message(body: bytes) -> dict[str, Any]:
    return json.loads(body.decode())


def is_http_ingested_event(raw: dict[str, Any]) -> bool:
    """php-river store() already persisted the row before publishing air.new."""
    return raw.get("event") == "sensor_data_ingested" and raw.get("id") is not None
