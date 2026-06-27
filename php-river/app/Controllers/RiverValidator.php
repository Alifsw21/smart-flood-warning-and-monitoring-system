<?php 

namespace App\Controllers;

class RiverValidator {

    private static function isBlank($value): bool {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return $value === '';
    }

    // Validasi input untuk SungaiController (store & update)
    public static function validateSungai(array $data): array {
        $errors = [];

        if (!isset($data['zoneId']) || self::isBlank($data['zoneId'])) {
            $errors[] = "Field 'zoneId' wajib diisi.";
        }
        if (!isset($data['lokasiSungai']) || self::isBlank($data['lokasiSungai'])) {
            $errors[] = "Field 'lokasiSungai' wajib diisi.";
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }

        return $data;
    }

    // Validasi input untuk ZoneController (store & update)
    public static function validateZone(array $data): array {
        $errors = [];

        if (!isset($data['nama_kota']) || self::isBlank($data['nama_kota'])) {
            $errors[] = "Field 'nama_kota' wajib diisi.";
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }

        return $data;
    }

    // Validasi input untuk SensorReadingController
    public static function validateReading(array $data): array {
        $errors = [];

        $required = [
            'idNode', 'tinggiAir', 'kelembapanTanah', 'curahHujan', 'suhuRataRata', 'kelembapanUdara', 'kecepatanAngin'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || self::isBlank($data[$field])) {
                $errors[] = "Field '$field' wajib diisi.";
            }
        }

        // Validasi tipe data numerik untuk FLOAT di database
        $numericFields = [
            'tinggiAir', 'kelembapanTanah', 'curahHujan', 'suhuRataRata', 'kelembapanUdara', 'kecepatanAngin'
        ];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = "Field '$field' harus berupa angka numerik.";
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }

        return $data;

    }

    // Validasi input untuk SensorNodeController
    public static function validateNode(array $data): array {
        $errors = [];
        $required = ['idSungai', 'idStation', 'namaNode', 'posisi', 'elevasi'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || self::isBlank($data[$field])) {
                $errors[] = "Field '$field' wajib diisi.";
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }

        return $data;
    }
}