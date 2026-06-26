<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

class DashboardController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */

    public function admin()
    {

        AuthMiddleware::handle();

        RoleMiddleware::admin();

        $this->view(

            'dashboard/admin',

            [

                'title' =>

                    'Admin Dashboard',

                'username' =>

                    Session::get(
                        'username'
                    ),

                'role' =>

                    Session::get(
                        'role'
                    )

            ]

        );

    }

    /*
    |--------------------------------------------------------------------------
    | User Dashboard
    |--------------------------------------------------------------------------
    */

    public function user()
    {

        AuthMiddleware::handle();

        RoleMiddleware::user();

        $this->view(

            'dashboard/user',

            [

                'title' =>

                    'User Dashboard',

                'username' =>

                    Session::get(
                        'username'
                    ),

                'role' =>

                    Session::get(
                        'role'
                    )

            ]

        );

    }

}