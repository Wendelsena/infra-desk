<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - InfraDesk</title>
</head>
<body>

    <h1>Dashboard</h1>

    <p>Bem-vindo, <?= htmlspecialchars($user['name']) ?>!</p>

    <p>Email: <?= htmlspecialchars($user['email']) ?></p>

    <p>Perfil: <?= htmlspecialchars($user['role']) ?></p>

    <br>

    <a href="logout.php">Sair</a>

</body>
</html>