<?php

namespace App\Controllers;

use App\Models\NotificationModel;
use App\Validators\NotificationValidator;

class NotifController extends BaseController
{
    private NotificationModel $model;
    private NotificationValidator $validator;

    public function __construct(NotificationModel $model)
    {
        $this->model = $model;
        $this->validator = new NotificationValidator();
    }

    public function index(int $userId): void
    {
        if ($userId <= 0) {
            $this->sendResponse(401, 'error', 'Pengguna Tidak Terautentikasi.');
        }

        $filters = $_GET;
        $errors = $this->validator->validateFilters($filters);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Filter Tidak Valid.', $errors);
        }

        try {
            $data = $this->model->getByUserId($userId, $filters);

            if (empty($data)) {
                $this->sendResponse(200, 'success', 'Belum Ada Notifikasi.', []);
            }

            $this->sendResponse(200, 'success', 'Berhasil Mengambil Notifikasi.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }
}
