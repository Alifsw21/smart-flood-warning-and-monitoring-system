#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Models\PeringatanModel;
use App\Services\RabbitMQAlertConsumer;

$model = new PeringatanModel();
$consumer = new RabbitMQAlertConsumer();

$consumer->consume(function (array $payload) use ($model) {
    $record = $model->createFromAlert($payload);
    $tipe = $record['tipePeringatan'] ?? 'unknown';
    $idSungai = $record['idSungai'] ?? '?';
    echo "Alert processed for sungai {$idSungai} ({$tipe})\n";
});
