<?php

namespace App\Services;

class OAuthService
{
    private string $baseUrl;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/app.php';

        $this->baseUrl = $config['oauth']['url'];
    }

    /*
    |--------------------------------------------------------------------------
    | Login JWT
    |--------------------------------------------------------------------------
    */

    public function login(
        string $username,
        string $password
    ): array {

        $payload = [

            'username' => $username,
            'password' => $password

        ];

        $curl = curl_init();

        curl_setopt_array($curl, [

            CURLOPT_URL =>

                $this->baseUrl .
                '/api/auth/login',

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_HTTPHEADER => [

                'Content-Type: application/json'

            ],

            CURLOPT_POSTFIELDS =>

                json_encode($payload)

        ]);

        $response = curl_exec($curl);

        if ($response === false) {

            throw new \Exception(
                'OAuth Server tidak dapat dihubungi.'
            );

        }

        curl_close($curl);

        return json_decode($response, true);
    }
}