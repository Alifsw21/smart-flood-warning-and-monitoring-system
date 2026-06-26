<?php

namespace App\Controllers;

use App\Services\OAuthService;
use App\Helpers\Session;

class AuthController extends Controller
{

    private OAuthService $oauth;

    public function __construct()
    {

        $this->oauth =
            new OAuthService();

    }

    /*
    |--------------------------------------------------------------------------
    | Login Page
    |--------------------------------------------------------------------------
    */

    public function index()
    {

        if (
            Session::has('role')
        ) {

            if (
                Session::get('role')
                ===
                'admin'
            ) {

                $this->redirect('/admin');

            }

            $this->redirect('/user');

        }

        $this->view(

            'auth/login',

            [

                'title' => 'Login'

            ]

        );

    }

    /*
    |--------------------------------------------------------------------------
    | Login JWT
    |--------------------------------------------------------------------------
    */

    public function login()
    {

        try {

            $username =
                trim(
                    $_POST['username']
                    ?? ''
                );

            $password =
                trim(
                    $_POST['password']
                    ?? ''
                );

            if (

                empty($username)

                ||

                empty($password)

            ) {

                $this->view(

                    'auth/login',

                    [

                        'title' =>

                            'Login',

                        'error' =>

                            'Username dan Password wajib diisi.'

                    ]

                );

                return;

            }

            $result =

                $this->oauth
                    ->login(

                        $username,

                        $password

                    );

            if (

                !isset(
                    $result['token']
                )

            ) {

                $this->view(

                    'auth/login',

                    [

                        'title' =>

                            'Login',

                        'error' =>

                            'Username atau Password salah.'

                    ]

                );

                return;

            }

            Session::put(

                'token',

                $result['token']

            );

            Session::put(

                'role',

                $result['role']

            );

            Session::put(

                'username',

                $result['username']

            );

            if (

                Session::get('role')

                ===

                'admin'

            ) {

                $this->redirect('/admin');

            }

            $this->redirect('/user');

        }

        catch (\Exception $e) {

            $this->view(

                'auth/login',

                [

                    'title' =>

                        'Login',

                    'error' =>

                        $e->getMessage()

                ]

            );

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Google Callback
    |--------------------------------------------------------------------------
    */

    public function callback()
    {

        if (

            !isset(
                $_GET['token']
            )

        ) {

            die(
                'Token tidak ditemukan.'
            );

        }

        Session::put(

            'token',

            $_GET['token']

        );

        Session::put(

            'role',

            $_GET['role']

        );

        Session::put(

            'username',

            $_GET['username']
            ??

            'Google User'

        );

        if (

            Session::get('role')

            ===

            'admin'

        ) {

            $this->redirect('/admin');

        }

        $this->redirect('/user');

    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout()
    {

        Session::destroy();

        $this->redirect('/');

    }

}