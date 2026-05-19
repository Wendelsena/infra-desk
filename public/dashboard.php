<?php

session_start();

require_once __DIR__ . '/../config/Connection.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

$pdo = Connection::connect();

$sql = "SELECT tickets.*, users.name AS user_name
        FROM tickets
        INNER JOIN users ON users.id = tickets.user_id
        ORDER BY tickets.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <a href="create-ticket.php">Novo chamado</a>
    |
    <a href="logout.php">Sair</a>

    <hr>

    <h2>Chamados</h2>

    <?php if (empty($tickets)): ?>
        <p>Nenhum chamado encontrado.</p>
    <?php else: ?>

        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Categoria</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th>Aberto por</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?= htmlspecialchars($ticket['id']) ?></td>
                        <td><?= htmlspecialchars($ticket['title']) ?></td>
                        <td><?= htmlspecialchars($ticket['category']) ?></td>
                        <td><?= htmlspecialchars($ticket['priority']) ?></td>
                        <td><?= htmlspecialchars($ticket['status']) ?></td>
                        <td><?= htmlspecialchars($ticket['user_name']) ?></td>
                        <td><?= htmlspecialchars($ticket['created_at']) ?></td>
                        <td>
                            <a href="view-ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

</body>
</html>