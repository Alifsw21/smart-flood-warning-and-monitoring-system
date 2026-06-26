<?php

use App\Helpers\Session;

?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">

    <div class="container">

        <a
            class="navbar-brand"
            href="/"
        >

            Flood Detection System

        </a>

        <?php if(Session::has('token')): ?>

            <div class="d-flex align-items-center">

                <span class="text-white me-3">

                    <?= Session::get('username'); ?>

                </span>

                <a
                    href="/logout"
                    class="btn btn-light btn-sm"
                >

                    Logout

                </a>

            </div>

        <?php endif; ?>

    </div>

</nav>