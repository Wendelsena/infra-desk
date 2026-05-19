<?php

session_start();

require_once __DIR__ . '/../config/Connection.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $priority = trim($_POST['priority']);
    $userId = $_SESSION['user']['id'];

    if (empty($title) || empty($description) || empty($category) || empty($priority)) {
        $message = "Preencha todos os campos.";
    } else {
        $allowedPriorities = [
            'baixa',
            'media',
            'alta'
        ];

        if (!in_array($priority, $allowedPriorities)) {
            $message = "Prioridade inválida.";
        } else {
            if ($priority === 'alta') {
                $dueAt = date('Y-m-d H:i:s', strtotime('+8 hours'));
            }

            if ($priority === 'media') {
                $dueAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            }

            if ($priority === 'baixa') {
                $dueAt = date('Y-m-d H:i:s', strtotime('+72 hours'));
            }

            $pdo = Connection::connect();

            $sql = "INSERT INTO tickets 
                    (
                        user_id,
                        title,
                        description,
                        category,
                        priority,
                        status,
                        due_at
                    )
                    VALUES 
                    (
                        :user_id,
                        :title,
                        :description,
                        :category,
                        :priority,
                        :status,
                        :due_at
                    )";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':description' => $description,
                ':category' => $category,
                ':priority' => $priority,
                ':status' => 'aberto',
                ':due_at' => $dueAt
            ]);

            header('Location: dashboard.php');
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo chamado - InfraDesk</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-4">
    <span class="navbar-brand mb-0 h1">
        InfraDesk
    </span>

    <a href="dashboard.php" class="text-warning text-decoration-none">
        Voltar
    </a>
</nav>

<div class="container mt-4">

    <div class="card shadow-sm">
        <div class="card-body">

            <h1 class="h3 mb-4">
                Novo chamado
            </h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">
                        Título
                    </label>

                    <input
                        type="text"
                        name="title"
                        class="form-control"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Descrição
                    </label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="5"
                        required
                    ></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Categoria
                    </label>

                    <select name="category" class="form-select" required>
                        <option value="">Selecione</option>
                        <option value="hardware">Hardware</option>
                        <option value="software">Software</option>
                        <option value="rede">Rede</option>
                        <option value="acesso">Acesso</option>
                        <option value="outros">Outros</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Prioridade
                    </label>

                    <select name="priority" class="form-select" required>
                        <option value="">Selecione</option>
                        <option value="baixa">Baixa - prazo de 72h</option>
                        <option value="media">Média - prazo de 24h</option>
                        <option value="alta">Alta - prazo de 8h</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    Abrir chamado
                </button>

            </form>

        </div>
    </div>

</div>

</body>
</html>