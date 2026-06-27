<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private $connection;
    private $channel;

    public function __construct()
    {
        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USER') ?: 'smartcity';
        $pass = getenv('RABBITMQ_PASS') ?: 'RabbitSecret';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();
    }

    public function publish($routingKey, array $dataArray)
    {
        $exchange = 'city.events';
        $this->channel->exchange_declare($exchange, 'topic', false, true, false);
        $this->channel->queue_declare($routingKey, false, true, false, false);
        $this->channel->queue_bind($routingKey, $exchange, $routingKey);

        $msg = new AMQPMessage(
            json_encode($dataArray),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $this->channel->basic_publish($msg, $exchange, $routingKey);
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
