<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQReportConsumer
{
    private const EXCHANGE = 'city.events';
    private const QUEUE = 'report.submitted';
    private const ROUTING_KEY = 'report.submitted';

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
        $this->channel->queue_declare(self::QUEUE, false, true, false, false);
        $this->channel->queue_bind(self::QUEUE, self::EXCHANGE, self::ROUTING_KEY);
    }

    public function consume(callable $handler): void
    {
        $callback = function (AMQPMessage $message) use ($handler) {
            try {
                $payload = json_decode($message->getBody(), true);
                if (!is_array($payload)) {
                    throw new \RuntimeException('Invalid report payload');
                }
                $handler($payload);
            } catch (\Throwable $e) {
                error_log('Report notification consumer error: ' . $e->getMessage());
            }
        };

        $this->channel->basic_consume(self::QUEUE, '', false, true, false, false, $callback);

        echo "php-user notification consumer stand-by on report.submitted\n";

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
