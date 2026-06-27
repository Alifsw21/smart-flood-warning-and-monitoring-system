<?php

namespace App\Validators;

class LaporanValidator
{
    public function validateId($id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return 'ID Harus Berupa Bilangan Bulat Positif.';
        }

        return null;
    }

    public function validateCreate(array $data)
    {
        $errors = [];

        if (empty($data['deskripsiLaporan'])) {
            $errors['deskripsiLaporan'] = 'Deskripsi Laporan Wajib Diisi.';
        } elseif (strlen($data['deskripsiLaporan']) < 10) {
            $errors['deskripsiLaporan'] = 'Deskripsi Laporan Minimal 10 Karakter.';
        }

        return $errors;
    }

    public function validateUpdate(array $data)
    {
        $errors = [];

        if (!isset($data['deskripsiLaporan']) || $data['deskripsiLaporan'] === '') {
            $errors['deskripsiLaporan'] = 'Deskripsi Laporan Wajib Diisi.';
        } elseif (strlen($data['deskripsiLaporan']) < 10) {
            $errors['deskripsiLaporan'] = 'Deskripsi Laporan Minimal 10 Karakter.';
        }

        return $errors;
    }

    public function validateFilters(array $filters)
    {
        $errors = [];

        if (isset($filters['idPengguna']) && $filters['idPengguna'] !== '' && filter_var($filters['idPengguna'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['idPengguna'] = 'idPengguna Harus Berupa Bilangan Bulat Positif.';
        }

        return $errors;
    }
}
