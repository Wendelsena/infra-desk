<?php

require_once __DIR__ . '/../config/Connection.php';

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($name) || empty($email) || empty($password)) {
        $message = "Preencha todos os campos.";
        $messageType = "danger";
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
            $messageType = "success";

        } catch (PDOException $e) {
            if ($e->getCode() == 23505) {
                $message = "Este email já está cadastrado.";
            } else {
                $message = "Erro ao cadastrar usuário.";
            }

            $messageType = "danger";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro - InfraDesk</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<div class="container min-vh-100 d-flex align-items-center justify-content-center">

    <div class="card shadow-sm border-0" style="max-width: 460px; width: 100%;">
        <div class="card-body p-4">

            <div class="text-center mb-4">
                <h1 class="h3 fw-bold mb-1">
                    Criar conta
                </h1>

                <p class="text-muted mb-0">
                    Cadastre-se para abrir e acompanhar chamados
                </p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">
                        Nome
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        placeholder="Digite seu nome"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="seuemail@exemplo.com"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Senha
                    </label>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Crie uma senha"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Cadastrar
                </button>

            </form>

            <div class="text-center mt-4">
                <span class="text-muted">
                    Já tem uma conta?
                </span>

                <a href="login.php" class="text-decoration-none">
                    Entrar
                </a>
            </div>

        </div>
    </div>

</div>

</body>
</html>