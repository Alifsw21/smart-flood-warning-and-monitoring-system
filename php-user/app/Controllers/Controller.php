<?php

namespace App\Controllers;

class Controller
{
    protected function view(
        string $view,
        array $data = []
    )
    {
        extract($data);

        $title =
            $data['title']
            ?? 'Flood Detection System';

        $content =
            __DIR__
            . '/../Views/'
            . $view
            . '.php';

        require
            __DIR__
            . '/../Views/layouts/app.php';
    }

    protected function redirect(
        string $url
    )
    {
        header("Location: {$url}");
        exit;
    }
}