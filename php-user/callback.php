<?php

session_start();

if(!isset($_GET['token'])){

    die("Token tidak ditemukan");
}

$_SESSION['token'] =
    $_GET['token'];

$_SESSION['role'] =
    $_GET['role'];

$_SESSION['username'] =
    $_GET['username'] ?? 'Google User';

if($_SESSION['role'] == 'admin'){

    header(
        "Location: admin_dashboard.php"
    );

}else{

    header(
        "Location: user_dashboard.php"
    );
}