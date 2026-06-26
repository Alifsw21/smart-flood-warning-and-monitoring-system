<?php

namespace App\Validators;

class PeringatanValidator {
    private $allowedTypes = ['normal', 'waspada', 'bencana'];

    public function validateId($id) {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return "ID Harus Berupa Bilangan Bulat Positif.";
        }

        return null;
    }

    public function validateFilters(array $filters) {
        $errors = [];

        if (isset($filters['idSungai']) && $filters['idSungai'] !== '' && filter_var($filters['idSungai'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors[] = "idSungai Harus Berupa Bilangan Bulat Positif.";
        }

        if (isset($filters['tipePeringatan']) && $filters['tipePeringatan'] !== '' && !in_array($filters['tipePeringatan'], $this->allowedTypes, true)) {
            $errors[] = "tipePeringatan Harus Bernilai normal, waspada, atau bencana.";
        }

        if (isset($filters['from']) && $filters['from'] !== '' && strtotime($filters['from']) === false) {
            $errors[] = "from Harus Berupa Tanggal yang Valid.";
        }

        if (isset($filters['to']) && $filters['to'] !== '' && strtotime($filters['to']) === false) {
            $errors[] = "to Harus Berupa Tanggal yang Valid.";
        }

        return $errors;
    }
}
