<?php

namespace App\Validators;

class NotificationValidator
{
    public function validateFilters(array $filters): array
    {
        $errors = [];

        if (isset($filters['is_read']) && $filters['is_read'] !== '' && !in_array((string) $filters['is_read'], ['0', '1'], true)) {
            $errors['is_read'] = 'is_read Harus 0 atau 1.';
        }

        return $errors;
    }
}
