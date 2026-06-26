<?php

namespace App\Helpers;

class Session
{

    /*
    |--------------------------------------------------------------------------
    | Put Session
    |--------------------------------------------------------------------------
    */

    public static function put(
        string $key,
        mixed $value
    ): void
    {

        $_SESSION[$key] = $value;

    }

    /*
    |--------------------------------------------------------------------------
    | Get Session
    |--------------------------------------------------------------------------
    */

    public static function get(
        string $key,
        mixed $default = null
    ): mixed
    {

        return $_SESSION[$key]
            ?? $default;

    }

    /*
    |--------------------------------------------------------------------------
    | Check Session
    |--------------------------------------------------------------------------
    */

    public static function has(
        string $key
    ): bool
    {

        return isset(
            $_SESSION[$key]
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Remove Session
    |--------------------------------------------------------------------------
    */

    public static function forget(
        string $key
    ): void
    {

        unset(
            $_SESSION[$key]
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Destroy Session
    |--------------------------------------------------------------------------
    */

    public static function destroy(): void
    {

        session_unset();

        session_destroy();

    }

}