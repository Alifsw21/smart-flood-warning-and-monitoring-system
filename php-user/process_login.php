<?php

session_start();

$username = $_POST['username'];
$password = $_POST['password'];

$data = [
    "username" => $username,
    "password" => $password
];

$curl = curl_init();

curl_setopt_array($curl, [

    CURLOPT_URL =>
        "http://localhost:3000/api/auth/login",

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],

    CURLOPT_POSTFIELDS =>
        json_encode($data)

]);

$response = curl_exec($curl);

if($response === false){

    die(
        "OAuth Server tidak dapat dihubungi"
    );
}

curl_close($curl);

$result = json_decode($response, true);

if(isset($result['token'])){

    $_SESSION['token'] =
        $result['token'];

    $_SESSION['role'] =
        $result['role'];

    $_SESSION['username'] =
        $result['username'];

    if($result['role'] == 'admin'){

        header(
            "Location: admin_dashboard.php"
        );

    } else {

        header(
            "Location: user_dashboard.php"
        );

    }

    exit;
}

echo "Login gagal";