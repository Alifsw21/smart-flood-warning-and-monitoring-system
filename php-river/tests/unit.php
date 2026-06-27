<?php

require_once dirname(__DIR__) . '/app/Validators/RiverValidator.php';

use App\Validators\RiverValidator;

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

function assert_exception($callback, $message) {
    try {
        $callback();
        assert_test(false, $message);
    } catch (InvalidArgumentException $e) {
        assert_test(true, $message);
    }
}

assert_test(
    RiverValidator::validateZone(['nama_kota' => 'Jakarta'])['nama_kota'] === 'Jakarta',
    'validateZone accepts valid nama_kota'
);

assert_exception(
    fn () => RiverValidator::validateZone(['nama_kota' => '']),
    'validateZone rejects empty nama_kota'
);

assert_test(
    RiverValidator::validateSungai(['zoneId' => 1, 'lokasiSungai' => 'Ciliwung'])['lokasiSungai'] === 'Ciliwung',
    'validateSungai accepts valid payload'
);

assert_exception(
    fn () => RiverValidator::validateSungai(['zoneId' => 'abc', 'lokasiSungai' => 'X']),
    'validateSungai rejects non-numeric zoneId'
);

assert_test(
    RiverValidator::validateNode([
        'idSungai' => 1,
        'idStation' => 500,
        'namaNode' => 'Node A',
        'posisi' => 'hulu',
        'elevasi' => 1.5,
    ])['namaNode'] === 'Node A',
    'validateNode accepts valid payload'
);

assert_exception(
    fn () => RiverValidator::validateNode([
        'idSungai' => 1,
        'idStation' => 500,
        'namaNode' => 'Node A',
        'posisi' => 'tengah',
        'elevasi' => 1.5,
    ]),
    'validateNode rejects invalid posisi'
);

assert_test(
    RiverValidator::validateReading([
        'idNode' => 1,
        'tinggiAir' => 1.2,
        'kelembapanTanah' => 20,
        'curahHujan' => 3,
        'suhuRataRata' => 28,
        'kelembapanUdara' => 70,
        'kecepatanAngin' => 2,
    ])['idNode'] == 1,
    'validateReading accepts numeric fields'
);

assert_exception(
    fn () => RiverValidator::validateReading([
        'idNode' => 'abc',
        'tinggiAir' => 1.2,
        'kelembapanTanah' => 20,
        'curahHujan' => 3,
        'suhuRataRata' => 28,
        'kelembapanUdara' => 70,
        'kecepatanAngin' => 2,
    ]),
    'validateReading rejects non-numeric idNode'
);

$total = $passed + $failed;
echo "$passed/$total unit tests passed\n";
exit($failed > 0 ? 1 : 0);
