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

        <div class="text-white">
            <?= htmlspecialchars($user['name']) ?>
            |
            <a href="logout.php" class="text-warning text-decoration-none">
                Sair
            </a>
        </div>
    </nav>

    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <h1 class="h3">
                Dashboard
            </h1>

            <a href="create-ticket.php" class="btn btn-primary">
                Novo chamado
            </a>

        </div>

        <div class="card shadow-sm">

            <div class="card-body">

                <?php if (empty($tickets)): ?>

                    <p class="text-muted">
                        Nenhum chamado encontrado.
                    </p>

                <?php else: ?>

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Usuário</th>
                                <th>Data</th>
                                <th></th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($tickets as $ticket): ?>

                                <?php

                                    $statusClass = 'secondary';

                                    if ($ticket['status'] === 'aberto') {
                                        $statusClass = 'warning';
                                    }

                                    if ($ticket['status'] === 'em andamento') {
                                        $statusClass = 'primary';
                                    }

                                    if ($ticket['status'] === 'finalizado') {
                                        $statusClass = 'success';
                                    }

                                    $priorityClass = 'secondary';

                                    if ($ticket['priority'] === 'alta') {
                                        $priorityClass = 'danger';
                                    }

                                    if ($ticket['priority'] === 'media') {
                                        $priorityClass = 'warning';
                                    }

                                    if ($ticket['priority'] === 'baixa') {
                                        $priorityClass = 'success';
                                    }

                                ?>

                                <tr>

                                    <td>
                                        #<?= htmlspecialchars($ticket['id']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($ticket['title']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($ticket['category']) ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-<?= $priorityClass ?>">
                                            <?= htmlspecialchars($ticket['priority']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= htmlspecialchars($ticket['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($ticket['user_name']) ?>
                                    </td>
                                    <td>

                                        <?php
                                            $formattedDate = (new DateTime($ticket['created_at']))
                                                ->format('d/m/Y H:i');
                                        ?>

                                        <?= $formattedDate ?>

                                    </td>
                                    <td>

                                        <a
                                            href="view-ticket.php?id=<?= $ticket['id'] ?>"
                                            class="btn btn-sm btn-dark"
                                        >
                                            Ver
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>

    </div>

    </body>
</html>