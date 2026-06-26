<?php

namespace App\Middleware;

use App\Helpers\Session;

class RoleMiddleware
{

    /*
    |--------------------------------------------------------------------------
    | Admin Role
    |--------------------------------------------------------------------------
    */

    public static function admin(): void
    {

        if (

            Session::get(
                'role'
            )

            !==

            'admin'

        ) {

            die(
                'Akses ditolak.'
            );

        }

    }

    /*
    |--------------------------------------------------------------------------
    | User Role
    |--------------------------------------------------------------------------
    */

    public static function user(): void
    {

        if (

            Session::get(
                'role'
            )

            !==

            'user'

        ) {

            die(
                'Akses ditolak.'
            );

        }

    }

}