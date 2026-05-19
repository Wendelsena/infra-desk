<?php

session_start();

require_once __DIR__ . '/../config/Connection.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = "Preencha email e senha.";
    } else {
        $pdo = Connection::connect();

        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];

            header('Location: dashboard.php');
            exit;
        } else {
            $message = "Email ou senha inválidos.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - InfraDesk</title>
</head>
<body>

    <h1>InfraDesk</h1>

    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Email:</label>
        <br>
        <input type="email" name="email" required>
        <br><br>

        <label>Senha:</label>
        <br>
        <input type="password" name="password" required>
        <br><br>

        <button type="submit">Entrar</button>
    </form>

    <br>

    <a href="register.php">Criar conta</a>

</body>
</html>