#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\NotificationModel;
use App\Services\RabbitMQCitizenEventConsumer;

$model = new NotificationModel();
$consumer = new RabbitMQCitizenEventConsumer();

$consumer->consume(
    function (array $payload) use ($model) {
        $record = $model->createFromReport($payload);
        $userId = $record['idPengguna'] ?? '?';
        $title = $record['title'] ?? 'notification';
        echo "Notification created for user {$userId}: {$title}\n";
    },
    function (array $payload) use ($model) {
        $records = $model->createFromAnomaly($payload);
        $count = count($records);
        $tipe = $payload['tipePeringatan'] ?? $payload['hasil_prediksi'] ?? 'alert';
        echo "Anomaly notifications created ({$count}) for alert type {$tipe}\n";
    }
);
