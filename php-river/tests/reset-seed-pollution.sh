#!/usr/bin/env bash
# Restore seed sensor node id=1 if polluted by prior test runs.
set -u

cd "$(dirname "$0")/../.."
php -r '
require "php-river/vendor/autoload.php";
Dotenv\Dotenv::createImmutable("php-river")->safeLoad();
require "php-river/database/database.php";
$db = (new Database())->getConnection();

$pollutedStations = [99999, 99998, 88881, 88882, 77771, 77772, 77773, 101];
$in = implode(",", array_map("intval", $pollutedStations));

$db->exec("DELETE rr FROM river_sensorReading rr INNER JOIN river_sensorNode n ON rr.idNode = n.id WHERE n.idStation IN ($in) OR n.id != 1 AND n.idStation = 101");
$db->exec("DELETE FROM river_sensorNode WHERE id != 1 AND (idStation IN ($in) OR namaNode IN (\"dup\",\"test\",\"Manual Node\",\"Del Test\",\"Node Integration\",\"Node Updated\",\"gate-test\"))");
$db->exec("DELETE rr FROM river_sensorReading rr INNER JOIN river_sensorNode n ON rr.idNode = n.id WHERE n.id != 1");
$db->exec("DELETE FROM river_sensorNode WHERE id != 1 AND idStation = 101");

$stmt = $db->prepare("UPDATE river_sensorNode SET idSungai=1, idStation=101, namaNode=\"Node Hulu Ciliwung\", posisi=\"hulu\", elevasi=13.4 WHERE id=1");
$stmt->execute();

$db->exec("DELETE FROM river_sungai WHERE lokasiSungai IN (\"Sungai Test Manual\",\"Sungai Integration Test\",\"Sungai Updated\")");
$db->exec("DELETE FROM river_zones WHERE nama_kota IN (\"Zona Integration Test\",\"Zona Updated\",\"Updated Zone\")");

echo "Seed cleanup done\n";
'
