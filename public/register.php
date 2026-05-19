<?php

require_once __DIR__ . '/../config/Connection.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($name) || empty($email) || empty($password)) {
        $message = "Preencha todos os campos.";
    } else {
        $pdo = Connection::connect();

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO users (name, email, password, role)
                    VALUES (:name, :email, :password, :role)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $passwordHash,
                ':role' => 'user'
            ]);

            $message = "Usuário cadastrado com sucesso!";

        } catch (PDOException $e) {
            if ($e->getCode() == 23505) {
                $message = "Este email já está cadastrado.";
            } else {
                $message = "Erro ao cadastrar usuário: " . $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro - InfraDesk</title>
</head>
<body>

    <h1>Cadastrar usuário</h1>

    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Nome:</label>
        <br>
        <input type="text" name="name" required>
        <br><br>

        <label>Email:</label>
        <br>
        <input type="email" name="email" required>
        <br><br>

        <label>Senha:</label>
        <br>
        <input type="password" name="password" required>
        <br><br>

        <button type="submit">Cadastrar</button>
    </form>

    <br>

    <a href="login.php">Já tenho conta</a>

</body>
</html>