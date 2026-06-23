<?php

session_start();

if(
    !isset($_SESSION['token'])
){

    header("Location:index.php");
    exit;
}

if(
    $_SESSION['role'] != 'user'
){

    die("Akses ditolak");
}
?>

<!DOCTYPE html>
<html>
<head>

<title>User Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<h1>User Dashboard</h1>

<p>Selamat datang
<b>
<?= $_SESSION['username']; ?>
</b>
</p>

<div class="alert alert-info">

Role :
<?= $_SESSION['role']; ?>

</div>

<a
href="logout.php"
class="btn btn-danger"
>
Logout
</a>

</div>

</body>
</html>