<?php

namespace App\Models;

class User
{

    public string $token;

    public string $username;

    public string $role;

    public function __construct(
        array $data
    )
    {

        $this->token =
            $data['token'];

        $this->username =
            $data['username'];

        $this->role =
            $data['role'];

    }

}