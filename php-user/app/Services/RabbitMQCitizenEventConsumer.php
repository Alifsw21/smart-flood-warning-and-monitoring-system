<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consumes citizen-facing events: report.submitted + citizen.anomaly.alert (Spec §4.5 / S6).
 */
class RabbitMQCitizenEventConsumer
{
    private const EXCHANGE = 'city.events';
    private const QUEUE_REPORT = 'report.submitted';
    private const QUEUE_ANOMALY = 'citizen.anomaly.alert';
    private const ROUTING_REPORT = 'report.submitted';
    private const ROUTING_ANOMALY = 'anomaly.alert';

    private $connection;
    private $channel;

    public function __construct()
    {
        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USER') ?: 'smartcity';
        $pass = getenv('RABBITMQ_PASS') ?: getenv('RABBITMQ_PASSWORD') ?: 'RabbitSecret';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);

        foreach (
            [
                [self::QUEUE_REPORT, self::ROUTING_REPORT],
                [self::QUEUE_ANOMALY, self::ROUTING_ANOMALY],
            ] as [$queue, $routingKey]
        ) {
            $this->channel->queue_declare($queue, false, true, false, false);
            $this->channel->queue_bind($queue, self::EXCHANGE, $routingKey);
        }
    }

    public function consume(callable $reportHandler, callable $anomalyHandler): void
    {
        $reportCallback = function (AMQPMessage $message) use ($reportHandler) {
            try {
                $payload = json_decode($message->getBody(), true);
                if (!is_array($payload)) {
                    throw new \RuntimeException('Invalid report payload');
                }
                $reportHandler($payload);
                $message->ack();
            } catch (\Throwable $e) {
                error_log('Report consumer error: ' . $e->getMessage());
                $message->nack(false, true);
            }
        };

        $anomalyCallback = function (AMQPMessage $message) use ($anomalyHandler) {
            try {
                $payload = json_decode($message->getBody(), true);
                if (!is_array($payload)) {
                    throw new \RuntimeException('Invalid anomaly payload');
                }
                $anomalyHandler($payload);
                $message->ack();
            } catch (\Throwable $e) {
                error_log('Anomaly notification consumer error: ' . $e->getMessage());
                $message->nack(false, true);
            }
        };

        $this->channel->basic_consume(self::QUEUE_REPORT, '', false, false, false, false, $reportCallback);
        $this->channel->basic_consume(self::QUEUE_ANOMALY, '', false, false, false, false, $anomalyCallback);

        echo "php-user citizen consumer stand-by on report.submitted + citizen.anomaly.alert\n";

        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
    }

    public function close(): void
    {
        $this->channel->close();
        $this->connection->close();
    }
}
