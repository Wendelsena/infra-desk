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
        $pdo = Connection::connect();

        $sql = "INSERT INTO tickets 
                (user_id, title, description, category, priority, status)
                VALUES 
                (:user_id, :title, :description, :category, :priority, :status)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':description' => $description,
            ':category' => $category,
            ':priority' => $priority,
            ':status' => 'aberto'
        ]);

        header('Location: dashboard.php');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo chamado - InfraDesk</title>
</head>
<body>

    <h1>Novo chamado</h1>

    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Título:</label>
        <br>
        <input type="text" name="title" required>
        <br><br>

        <label>Descrição:</label>
        <br>
        <textarea name="description" rows="5" cols="40" required></textarea>
        <br><br>

        <label>Categoria:</label>
        <br>
        <select name="category" required>
            <option value="">Selecione</option>
            <option value="hardware">Hardware</option>
            <option value="software">Software</option>
            <option value="rede">Rede</option>
            <option value="acesso">Acesso</option>
            <option value="outros">Outros</option>
        </select>
        <br><br>

        <label>Prioridade:</label>
        <br>
        <select name="priority" required>
            <option value="">Selecione</option>
            <option value="baixa">Baixa</option>
            <option value="media">Média</option>
            <option value="alta">Alta</option>
        </select>
        <br><br>

        <button type="submit">Abrir chamado</button>
    </form>

    <br>

    <a href="dashboard.php">Voltar</a>

</body>
</html>