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

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<div class="container min-vh-100 d-flex align-items-center justify-content-center">

    <div class="card shadow-sm border-0" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4">

            <div class="text-center mb-4">
                <h1 class="h3 fw-bold mb-1">
                    InfraDesk
                </h1>

                <p class="text-muted mb-0">
                    Acesse sua conta para gerenciar chamados
                </p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

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
                        placeholder="Digite sua senha"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Entrar
                </button>

            </form>

            <div class="text-center mt-4">
                <span class="text-muted">
                    Ainda não tem conta?
                </span>

                <a href="register.php" class="text-decoration-none">
                    Criar conta
                </a>
            </div>

        </div>
    </div>

</div>

</body>
</html>