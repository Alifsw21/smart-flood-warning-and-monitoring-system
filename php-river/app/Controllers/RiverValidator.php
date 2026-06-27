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

        if (!is_numeric($data['zoneId'])) {
            throw new \InvalidArgumentException("Field 'zoneId' harus berupa angka numerik.");
        }

        return $data;
    }

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

        $numericFields = [
            'idNode', 'tinggiAir', 'kelembapanTanah', 'curahHujan', 'suhuRataRata', 'kelembapanUdara', 'kecepatanAngin', 'arahAngin'
        ];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null && !is_numeric($data[$field])) {
                $errors[] = "Field '$field' harus berupa angka numerik.";
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }

        return $data;
    }

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

        if (!in_array($data['posisi'], ['hulu', 'hilir'], true)) {
            throw new \InvalidArgumentException("Field 'posisi' harus 'hulu' atau 'hilir'.");
        }

        $numericFields = ['idSungai', 'idStation', 'elevasi'];
        foreach ($numericFields as $field) {
            if (!is_numeric($data[$field])) {
                throw new \InvalidArgumentException("Field '$field' harus berupa angka numerik.");
            }
        }

        return $data;
    }
}
