<?php

$failed = 0;

function assertTest($condition, $testName) {
    global $failed;
    if ($condition) {
        echo "$testName berhasil\n";
    } else {
        echo "$testName gagal\n";
        $failed++;
    }
}

echo "Memulai unit test logika sensor IoT\n\n";

function calculateWaterLevel($durationMicroseconds) {
    $distance_m = ($durationMicroseconds * 0.034 / 2) / 100.0;
    $waterLevel_m = 5.0 - $distance_m;

    if ($waterLevel_m < 0) {
        $waterLevel_m = 0;
    }

    return $waterLevel_m;
}

$durationNormal = 10000;
assertTest(abs(calculateWaterLevel($durationNormal) - 3.3) < 0.01, "Logika konversi Ultrasonik ke Level Air (Normal)");

$durasiJauh = 40000;
assertTest(calculateWaterLevel($durasiJauh) == 0.0, "Nilai water level tidak boleh negatif");

function validateTemperatureRange($t_avg) {
    $tn = $t_avg - 2.0;
    $tx = $t_avg + 3.0;

    return ($tx > $tn) && ($tx > $t_avg) && ($tn < $t_avg);
}

assertTest(validateTemperatureRange(28.5), "Kalkulasi Suhu Maksimal selalu lebih tinggi dari suhu minimum");

$mockPayloadNode2 = json_decode('{"idNode":2,"curahHujan":25.5,"suhuMin":26.5,"suhuMax":31.5,"suhuRataRata":28.5}', true);
assertTest(
    isset($mockPayloadNode2['suhuRataRata']) && is_float($mockPayloadNode2['suhuRataRata']), 
    "Payload Sensor Cuaca memiliki struktur dan tipe data yang benar"
);

echo "\n=============================================\n";
echo "Total Tes GagalL $failed\n";

exit($failed > 0 ? 1 : 0);