<?php
/**
 * Full php-river integration tests — requires MySQL with seed data loaded.
 * Usage: php php-river/tests/integration.php [baseUrl]
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8098', '/');
$passed = 0;
$failed = 0;
$createdZoneId = null;
$createdSungaiId = null;
$createdSensorId = null;

function pass(string $message): void {
    global $passed;
    $passed++;
    echo "PASS $message\n";
}

function fail(string $message, string $detail = ''): void {
    global $failed;
    $failed++;
    echo "FAIL $message\n";
    if ($detail !== '') {
        echo "      $detail\n";
    }
}

function request(string $method, string $path, array $options = []): array {
    global $baseUrl;

    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;

    $curlHeaders = [];
    foreach ($headers as $key => $value) {
        $curlHeaders[] = "$key: $value";
    }

    if ($body !== null && !isset($headers['Content-Type'])) {
        $curlHeaders[] = 'Content-Type: application/json';
    }

    $ch = curl_init($baseUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => $curlHeaders,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }

    $responseBody = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => is_string($responseBody) ? $responseBody : '',
        'json' => json_decode(is_string($responseBody) ? $responseBody : '', true),
    ];
}

function assertStatus(array $response, int $expected, string $label): void {
    if ($response['status'] === $expected) {
        pass("$label (HTTP {$response['status']})");
        return;
    }

    fail($label, "expected HTTP $expected, got {$response['status']} — body: " . substr($response['body'], 0, 200));
}

function assertSuccess(array $response, string $label): void {
    if (($response['json']['status'] ?? '') === 'success') {
        pass($label);
        return;
    }

    fail($label, 'body: ' . substr($response['body'], 0, 300));
}

function assertBodyContains(array $response, string $needle, string $label): void {
    if (str_contains($response['body'], $needle)) {
        pass($label);
        return;
    }

    fail($label, 'body: ' . substr($response['body'], 0, 300));
}

echo "=== Health & routing ===\n";
$r = request('GET', '/health');
assertStatus($r, 200, 'GET /health');
assertSuccess($r, 'health success');

$r = request('GET', '/missing');
assertStatus($r, 404, 'GET unknown path');

$r = request('POST', '/health');
assertStatus($r, 405, 'POST /health');

echo "\n=== Zones ===\n";
$r = request('GET', '/api/river/zones');
assertStatus($r, 200, 'GET zones');

$r = request('POST', '/api/river/zones', ['body' => ['nama_kota' => 'X']]);
assertStatus($r, 403, 'POST zone no admin');

$r = request('POST', '/api/river/zones', [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => ['nama_kota' => ''],
]);
assertStatus($r, 400, 'POST zone empty');

$r = request('POST', '/api/river/zones', [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => ['nama_kota' => 'Zona Integration Test'],
]);
assertStatus($r, 201, 'POST zone admin');
$createdZoneId = $r['json']['data']['id'] ?? null;
$createdZoneId ? pass("zone id=$createdZoneId") : fail('zone id missing');

$r = request('GET', "/api/river/zones/$createdZoneId");
assertStatus($r, 200, 'GET zone by id');
assertBodyContains($r, 'Zona Integration Test', 'zone nama_kota');

$r = request('PUT', "/api/river/zones/$createdZoneId", [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => ['nama_kota' => 'Zona Updated'],
]);
assertStatus($r, 200, 'PUT zone admin');

echo "\n=== Sungai ===\n";
$r = request('POST', '/api/river/sungai', [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => ['zoneId' => (int)$createdZoneId, 'lokasiSungai' => 'Sungai Integration Test'],
]);
assertStatus($r, 201, 'POST sungai admin');
$createdSungaiId = $r['json']['data']['id'] ?? null;
$createdSungaiId ? pass("sungai id=$createdSungaiId") : fail('sungai id missing');

$r = request('GET', "/api/river/sungai/$createdSungaiId");
assertStatus($r, 200, 'GET sungai by id');
assertBodyContains($r, 'Sungai Integration Test', 'sungai lokasi');

$r = request('PUT', "/api/river/sungai/$createdSungaiId", [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => ['zoneId' => (int)$createdZoneId, 'lokasiSungai' => 'Sungai Updated'],
]);
assertStatus($r, 200, 'PUT sungai admin');

echo "\n=== Sensors ===\n";
$r = request('GET', '/api/river/sensors/999999');
assertStatus($r, 404, 'GET missing sensor');

$r = request('POST', '/api/river/sensors', [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => [
        'idSungai' => (int)$createdSungaiId,
        'idStation' => 77772,
        'namaNode' => 'Bad',
        'posisi' => 'tengah',
        'elevasi' => 1,
    ],
]);
assertStatus($r, 400, 'POST invalid posisi');

$r = request('POST', '/api/river/sensors', [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => [
        'idSungai' => (int)$createdSungaiId,
        'idStation' => 77772,
        'namaNode' => 'Node Integration',
        'posisi' => 'hulu',
        'elevasi' => 5.5,
    ],
]);
assertStatus($r, 201, 'POST sensor admin');
$createdSensorId = $r['json']['data']['id'] ?? null;
$createdSensorId ? pass("sensor id=$createdSensorId") : fail('sensor id missing');

$r = request('GET', "/api/river/sensors/$createdSensorId");
assertStatus($r, 200, 'GET sensor by id');
assertBodyContains($r, 'Node Integration', 'sensor namaNode');

$r = request('PUT', "/api/river/sensors/$createdSensorId", [
    'headers' => ['X-User-Role' => 'admin'],
    'body' => [
        'idSungai' => (int)$createdSungaiId,
        'idStation' => 77773,
        'namaNode' => 'Node Updated',
        'posisi' => 'hilir',
        'elevasi' => 6,
    ],
]);
assertStatus($r, 200, 'PUT sensor admin');

$r = request('GET', "/api/river/sensors/$createdSensorId");
assertBodyContains($r, 'Node Updated', 'sensor updated');
assertBodyContains($r, 'hilir', 'sensor posisi hilir');

echo "\n=== Readings & spec endpoints ===\n";
$r = request('GET', '/api/river/readings?nodeId=1&limit=2');
assertStatus($r, 200, 'GET readings by nodeId');

$r = request('GET', '/api/river/readings?zoneId=1&limit=2');
assertStatus($r, 200, 'GET readings by zoneId');

$r = request('GET', '/api/traffic/history?zoneId=1&from=2026-06-18&to=2026-06-20&limit=5');
assertStatus($r, 200, 'GET traffic history');

$r = request('GET', '/api/environment/current');
assertStatus($r, 200, 'GET environment current');
assertSuccess($r, 'environment current success');

$r = request('GET', '/api/environment/current?zoneId=1');
assertStatus($r, 200, 'GET environment current zone filter');

$r = request('GET', '/api/traffic/current');
assertStatus($r, 200, 'GET traffic current');

$r = request('POST', '/api/environment/readings', ['body' => []]);
assertStatus($r, 400, 'POST reading empty');

$readingPayload = [
    'idNode' => (int)$createdSensorId,
    'tinggiAir' => 2.5,
    'kelembapanTanah' => 30,
    'curahHujan' => 5,
    'suhuRataRata' => 28,
    'kelembapanUdara' => 70,
    'kecepatanAngin' => 3.5,
    'arahAngin' => 180,
];

$r = request('POST', '/api/environment/readings', ['body' => $readingPayload]);
assertStatus($r, 201, 'POST environment reading');

$r = request('POST', '/api/traffic/readings', ['body' => $readingPayload]);
assertStatus($r, 201, 'POST traffic reading');

$r = request('GET', "/api/river/sensors/$createdSensorId/readings?limit=1");
assertStatus($r, 200, 'GET sensor readings');
assertBodyContains($r, '2.5', 'reading tinggiAir');

echo "\n=== Cleanup ===\n";
$r = request('DELETE', "/api/river/sensors/$createdSensorId", ['headers' => ['X-User-Role' => 'admin']]);
assertStatus($r, 200, 'DELETE sensor with readings');

$r = request('GET', "/api/river/sensors/$createdSensorId");
assertStatus($r, 404, 'GET deleted sensor');

$r = request('DELETE', "/api/river/sungai/$createdSungaiId", ['headers' => ['X-User-Role' => 'admin']]);
assertStatus($r, 200, 'DELETE sungai');

$r = request('DELETE', "/api/river/zones/$createdZoneId", ['headers' => ['X-User-Role' => 'admin']]);
assertStatus($r, 200, 'DELETE zone');

$r = request('DELETE', '/api/river/sensors/1');
assertStatus($r, 403, 'DELETE seed sensor no admin');

$total = $passed + $failed;
echo "\n=== Results: $passed/$total passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
