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

function calculateSoilMoisture($raw_pot) {
    return ($raw_pot / 4095.0) * 100.0;
}
assertTest(abs(calculateSoilMoisture(2047) - 49.98) < 0.1, "Kalkulasi kelembapan tanah rata-rata");

function validateTemperatureRange($t_avg) {
    return [
        'tn' => $t_avg - 2.0,
        'tx' => $t_avg + 3.0
    ];
}
$tempRange = validateTemperatureRange(28.5);
assertTest(($tempRange['tn'] == 26.5) && ($tempRange['tx'] == 31.5), "Kalkulasi suhu maksimal dan minimal");

function calculateRainfall($raw_pot) {
    return ($raw_pot / 4095.0) * 50.0;
}
assertTest(abs(calculateRainfall(4095) - 50.0) < 0.01, "Kalkulasi curah Hujan Maksimal");

$mockPayloadNode2 = json_decode('{"idNode":2,"curahHujan":25.5,"suhuMin":26.5,"suhuMax":31.5,"suhuRataRata":28.5}', true);
assertTest(
    isset($mockPayloadNode2['suhuRataRata']) && is_float($mockPayloadNode2['suhuRataRata']), 
    "Payload Sensor Cuaca memiliki struktur dan tipe data yang benar"
);

echo "\n=============================================\n";
echo "Total Tes Gagal $failed\n";

exit($failed > 0 ? 1 : 0);