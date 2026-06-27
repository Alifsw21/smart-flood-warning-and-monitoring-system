<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Validators\FloodHistoryValidator;
use App\Validators\LaporanValidator;
use App\Validators\UserValidator;

$passed = 0;
$failed = 0;

function assert_test($condition, $message)
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "PASS $message\n";
        return;
    }

    $failed++;
    echo "FAIL $message\n";
}

$floodValidator = new FloodHistoryValidator();

assert_test($floodValidator->validateId('1') === null, 'flood validateId accepts positive integer');
assert_test($floodValidator->validateId('0') !== null, 'flood validateId rejects zero');
assert_test(empty($floodValidator->validateFilters(['status' => 'ringan'])), 'flood validateFilters accepts ringan');

$userValidator = new UserValidator();

assert_test(empty($userValidator->validateCreate([
    'username' => 'testuser',
    'password' => 'secret12',
    'role' => 'user',
], true)), 'user validateCreate accepts valid payload');
assert_test(isset($userValidator->validateCreate([
    'username' => '',
    'password' => 'secret12',
], true)['username']), 'user validateCreate rejects empty username');
assert_test(isset($userValidator->validateUpdate(['role' => 'admin'], false)['role']), 'user validateUpdate blocks role change for non-admin');

$laporanValidator = new LaporanValidator();

assert_test(empty($laporanValidator->validateCreate([
    'deskripsiLaporan' => 'Jalan depan rumah tergenang air setinggi lutut.',
])), 'laporan validateCreate accepts valid description');
assert_test(isset($laporanValidator->validateCreate([
    'deskripsiLaporan' => 'pendek',
])['deskripsiLaporan']), 'laporan validateCreate rejects short description');

$total = $passed + $failed;
echo "$passed/$total unit tests passed\n";

exit($failed > 0 ? 1 : 0);
