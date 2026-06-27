<?php

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

require_once dirname(__DIR__) . '/app/Models/PeringatanModel.php';
require_once dirname(__DIR__) . '/app/Validators/PeringatanValidator.php';
require_once dirname(__DIR__) . '/app/Controllers/PeringatanController.php';

use App\Models\PeringatanModel;
use App\Validators\PeringatanValidator;

$passed = 0;
$failed = 0;

function assert_test($condition, $message) {
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "PASS $message\n";
        return;
    }

    $failed++;
    echo "FAIL $message\n";
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE river_sungai (id INTEGER PRIMARY KEY, lokasiSungai TEXT)");
$pdo->exec("CREATE TABLE analytics_peringatan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    idSungai INTEGER,
    tipePeringatan TEXT DEFAULT 'normal',
    nilaiProbabilitas REAL DEFAULT 0.0,
    recorded_at TEXT
)");
$pdo->exec("CREATE TABLE user_riwayatBanjir (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    idSungai INTEGER,
    tinggiAir REAL,
    status TEXT
)");

$riverStmt = $pdo->prepare("INSERT INTO river_sungai (id, lokasiSungai) VALUES (:id, :lokasiSungai)");
$riverStmt->execute([':id' => 1, ':lokasiSungai' => 'Sungai Ciliwung']);
$riverStmt->execute([':id' => 2, ':lokasiSungai' => 'Sungai Cisadane']);

$warningStmt = $pdo->prepare("INSERT INTO analytics_peringatan (id, idSungai, tipePeringatan, nilaiProbabilitas, recorded_at)
    VALUES (:id, :idSungai, :tipePeringatan, :nilaiProbabilitas, :recorded_at)");
$warningStmt->execute([
    ':id' => 1,
    ':idSungai' => 1,
    ':tipePeringatan' => 'normal',
    ':nilaiProbabilitas' => 0.10,
    ':recorded_at' => '2025-01-10 08:00:00',
]);
$warningStmt->execute([
    ':id' => 2,
    ':idSungai' => 1,
    ':tipePeringatan' => 'waspada',
    ':nilaiProbabilitas' => 0.50,
    ':recorded_at' => '2025-01-18 09:00:00',
]);
$warningStmt->execute([
    ':id' => 3,
    ':idSungai' => 2,
    ':tipePeringatan' => 'bencana',
    ':nilaiProbabilitas' => 0.85,
    ':recorded_at' => '2025-01-25 10:00:00',
]);
$warningStmt->execute([
    ':id' => 4,
    ':idSungai' => 2,
    ':tipePeringatan' => 'bencana',
    ':nilaiProbabilitas' => 0.95,
    ':recorded_at' => '2025-02-01 11:00:00',
]);

$model = new PeringatanModel($pdo);

$all = $model->getAll([]);
assert_test(count($all) === 4, 'getAll([]) returns 4 rows');
assert_test($all[0]['recorded_at'] >= $all[1]['recorded_at'], 'getAll([]) orders by recorded_at DESC');
assert_test(count($model->getAll(['tipePeringatan' => 'bencana'])) === 2, 'getAll() filters tipePeringatan');
assert_test(count($model->getAll(['idSungai' => 1])) === 2, 'getAll() filters idSungai');
assert_test(count($model->getAll(['from' => '2025-01-18', 'to' => '2025-01-26'])) === 2, 'getAll() filters date range');

$row = $model->getById(1);
assert_test($row !== null && array_key_exists('lokasiSungai', $row), 'getById(1) returns row with lokasiSungai');
assert_test($model->getById(999) === null, 'getById(999) returns null');
assert_test($model->delete(1) === 1, 'delete(1) returns 1');
assert_test($model->delete(999) === 0, 'delete(999) returns 0');
assert_test(count($model->getAll([])) === 3, 'getAll([]) after delete returns 3 rows');
assert_test($model->ping() === true, 'ping() returns true');

$created = $model->createFromAlert([
    'idSungai' => 1,
    'tipePeringatan' => 'waspada',
    'nilaiProbabilitas' => 0.72,
    'tinggiAir' => 3.8,
]);
assert_test($created['tipePeringatan'] === 'waspada', 'createFromAlert() stores waspada alert');
$historyCount = (int) $pdo->query('SELECT COUNT(*) FROM user_riwayatBanjir')->fetchColumn();
assert_test($historyCount === 1, 'createFromAlert() inserts riwayat for waspada/bencana');

$validator = new PeringatanValidator();
assert_test($validator->validateId('5') === null, 'validateId("5") returns null');
assert_test($validator->validateId('abc') !== null, 'validateId("abc") returns error');
assert_test($validator->validateId('0') !== null, 'validateId("0") returns error');
assert_test($validator->validateId('-1') !== null, 'validateId("-1") returns error');
assert_test($validator->validateFilters([]) === [], 'validateFilters([]) returns empty array');
assert_test(count($validator->validateFilters(['tipePeringatan' => 'invalid'])) > 0, 'validateFilters() rejects invalid tipePeringatan');
assert_test(count($validator->validateFilters(['idSungai' => 'notint'])) > 0, 'validateFilters() rejects invalid idSungai');
assert_test(count($validator->validateFilters(['from' => 'notadate'])) > 0, 'validateFilters() rejects invalid from');
assert_test($validator->validateFilters([
    'tipePeringatan' => 'bencana',
    'idSungai' => '2',
    'from' => '2025-01-01',
    'to' => '2025-12-31',
]) === [], 'validateFilters() accepts valid filters');

$total = $passed + $failed;
echo "$passed/$total tests passed\n";

if ($failed > 0) {
    exit(1);
}
