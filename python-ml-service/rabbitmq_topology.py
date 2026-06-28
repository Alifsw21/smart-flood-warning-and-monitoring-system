"""RabbitMQ topology aligned with spec §4.5 (city.events topic exchange)."""

from __future__ import annotations

import json
import os

import pika

CITY_EVENTS_EXCHANGE = "city.events"
CITY_COMMANDS_EXCHANGE = "city.commands"

ROUTING_AIR_NEW = "air.new"
ROUTING_TRAFFIC_NEW = "traffic.new"
ROUTING_ANOMALY_ALERT = "anomaly.alert"
ROUTING_REPORT_SUBMITTED = "report.submitted"
# Fan-out queue for php-user S6 citizen notifications (same routing key as analytics queue).
QUEUE_CITIZEN_ANOMALY_ALERT = "citizen.anomaly.alert"

EVENT_QUEUES = (
    ROUTING_AIR_NEW,
    ROUTING_TRAFFIC_NEW,
    ROUTING_ANOMALY_ALERT,
    ROUTING_REPORT_SUBMITTED,
)

EVENT_FANOUT_BINDINGS = (
    (QUEUE_CITIZEN_ANOMALY_ALERT, ROUTING_ANOMALY_ALERT),
)


def rabbitmq_credentials() -> pika.PlainCredentials:
    return pika.PlainCredentials(
        os.environ.get("RABBITMQ_USER", "guest"),
        os.environ.get("RABBITMQ_PASSWORD", "guest"),
    )


def rabbitmq_parameters() -> pika.ConnectionParameters:
    return pika.ConnectionParameters(
        host=os.environ.get("RABBITMQ_HOST", "127.0.0.1"),
        port=int(os.environ.get("RABBITMQ_PORT", "5672")),
        virtual_host=os.environ.get("RABBITMQ_VHOST", "/"),
        credentials=rabbitmq_credentials(),
    )


def setup_events_topology(channel: pika.channel.Channel) -> None:
    channel.exchange_declare(
        exchange=CITY_EVENTS_EXCHANGE,
        exchange_type="topic",
        durable=True,
    )
    for queue_name in EVENT_QUEUES:
        channel.queue_declare(queue=queue_name, durable=True)
        channel.queue_bind(
            queue=queue_name,
            exchange=CITY_EVENTS_EXCHANGE,
            routing_key=queue_name,
        )
    for queue_name, routing_key in EVENT_FANOUT_BINDINGS:
        channel.queue_declare(queue=queue_name, durable=True)
        channel.queue_bind(
            queue=queue_name,
            exchange=CITY_EVENTS_EXCHANGE,
            routing_key=routing_key,
        )


def publish_event(channel: pika.channel.Channel, routing_key: str, payload: dict) -> None:
    setup_events_topology(channel)
    channel.basic_publish(
        exchange=CITY_EVENTS_EXCHANGE,
        routing_key=routing_key,
        body=json.dumps(payload),
        properties=pika.BasicProperties(delivery_mode=2),
    )
