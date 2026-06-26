<?php

use App\Controllers\AuthController;
use App\Controllers\DashboardController;

return [

    'GET' => [

        '/' => [

            AuthController::class,

            'index'

        ],

        '/callback' => [

            AuthController::class,

            'callback'

        ],

        '/logout' => [

            AuthController::class,

            'logout'

        ],

        '/admin' => [

            DashboardController::class,

            'admin'

        ],

        '/user' => [

            DashboardController::class,

            'user'

        ]

    ],

    'POST' => [

        '/login' => [

            AuthController::class,

            'login'

        ]

    ]

];