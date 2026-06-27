<?php

namespace App\Validators;

class FloodHistoryValidator
{
    public function validateId($id)
    {
        if (!is_numeric($id)) {
            return "ID harus berupa angka.";
        }

        if ((int)$id <= 0) {
            return "ID harus lebih besar dari 0.";
        }

        return null;
    }

    public function validateFilters($filters)
    {
        $errors = [];

        if (isset($filters['status'])) {

            $allowedStatus = [
                'ringan',
                'sedang',
                'tinggi'
            ];

            if (!in_array($filters['status'], $allowedStatus)) {
                $errors['status'] =
                    "Status hanya boleh: ringan, sedang, atau tinggi.";
            }
        }

        if (isset($filters['idSungai'])) {

            if (!is_numeric($filters['idSungai'])) {
                $errors['idSungai'] =
                    "idSungai harus berupa angka.";
            }
        }

        return $errors;
    }
}