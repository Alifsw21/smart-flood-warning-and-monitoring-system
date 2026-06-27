<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Validators\FloodHistoryValidator;

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

$validator = new FloodHistoryValidator();

assert_test($validator->validateId('1') === null, 'validateId accepts positive integer');
assert_test($validator->validateId('0') !== null, 'validateId rejects zero');
assert_test($validator->validateId('abc') !== null, 'validateId rejects non-numeric');

$validFilters = $validator->validateFilters(['status' => 'ringan', 'idSungai' => '2']);
assert_test(empty($validFilters), 'validateFilters accepts valid status and idSungai');

$invalidStatus = $validator->validateFilters(['status' => 'ekstrem']);
assert_test(isset($invalidStatus['status']), 'validateFilters rejects invalid status');

$invalidRiver = $validator->validateFilters(['idSungai' => '0']);
assert_test(isset($invalidRiver['idSungai']), 'validateFilters rejects non-positive idSungai');

$total = $passed + $failed;
echo "$passed/$total unit tests passed\n";

exit($failed > 0 ? 1 : 0);
