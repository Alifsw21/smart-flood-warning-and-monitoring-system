<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private $connection;
    private $channel;

    public function __construct() {
        $host = $_ENV['RABBITMQ_HOST'];
        $port = $_ENV['RABBITMQ_PORT'];
        $user = $_ENV['RABBITMQ_USER'];
        $pass = $_ENV['RABBITMQ_PASS'];

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();
    }

    public function publish($queueName, $dataArray) {
        $this->channel->queue_declare($queueName, false, true, false, false);

        $jsonMessage = json_encode($dataArray);
        $msg = new AMQPMessage($jsonMessage, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        $this->channel->basic_publish($msg, '', $queueName);
    }

    public function consume($queueName, $callbackFunction) {
        $this->channel->queue_declare($queueName, false, true, false, false);

        echo "RabbitMQ standby";

        $this->channel->basic_consume($queueName, '', false, true, false, false, $callbackFunction);

        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
    }

    public function close() {
        $this->channel->close();
        $this->connection->close();
    }
}