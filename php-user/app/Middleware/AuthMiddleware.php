<?php

namespace App\Middleware;

use App\Helpers\Session;

class AuthMiddleware
{

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public static function handle(): void
    {

        if (

            !Session::has(
                'token'
            )

        ) {

            header(
                'Location: /'
            );

            exit;

        }

    }

}