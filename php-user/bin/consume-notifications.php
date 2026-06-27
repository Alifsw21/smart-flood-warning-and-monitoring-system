#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\NotificationModel;
use App\Services\RabbitMQReportConsumer;

$model = new NotificationModel();
$consumer = new RabbitMQReportConsumer();

$consumer->consume(function (array $payload) use ($model) {
    $record = $model->createFromReport($payload);
    $userId = $record['idPengguna'] ?? '?';
    $title = $record['title'] ?? 'notification';
    echo "Notification created for user {$userId}: {$title}\n";
});
