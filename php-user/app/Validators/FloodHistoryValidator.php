<?php

namespace App\Validators;

class FloodHistoryValidator
{
    private $allowedStatus = ['ringan', 'sedang', 'tinggi'];

    public function validateId($id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return 'ID Harus Berupa Bilangan Bulat Positif.';
        }

        return null;
    }

    public function validateFilters(array $filters)
    {
        $errors = [];

        if (isset($filters['status']) && $filters['status'] !== '' && !in_array($filters['status'], $this->allowedStatus, true)) {
            $errors['status'] = 'Status Hanya Boleh: ringan, sedang, atau tinggi.';
        }

        if (isset($filters['idSungai']) && $filters['idSungai'] !== '' && filter_var($filters['idSungai'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['idSungai'] = 'idSungai Harus Berupa Bilangan Bulat Positif.';
        }

        return $errors;
    }
}
