<?php

session_start();

require_once __DIR__ . '/../config/Connection.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$pdo = Connection::connect();

$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role'];

$canManageTicket = in_array($userRole, ['ti', 'admin']);

if (!$ticketId) {
    header('Location: dashboard.php');
    exit;
}

$sql = "SELECT tickets.*, users.name AS user_name
        FROM tickets
        INNER JOIN users ON users.id = tickets.user_id
        WHERE tickets.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':id' => $ticketId
]);

$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

$isTicketOwner = ((int) $ticket['user_id'] === (int) $userId);

if (!$isTicketOwner && !$canManageTicket) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['comment'])) {

        $comment = trim($_POST['comment']);

        if (!empty($comment)) {

            $sql = "INSERT INTO comments 
                    (ticket_id, user_id, comment)
                    VALUES 
                    (:ticket_id, :user_id, :comment)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':user_id' => $userId,
                ':comment' => $comment
            ]);

            header("Location: view-ticket.php?id=" . $ticketId);
            exit;
        }
    }

    if (isset($_POST['status']) && $canManageTicket) {

        $status = trim($_POST['status']);

        $allowedStatus = [
            'aberto',
            'em andamento',
            'finalizado'
        ];

        if (in_array($status, $allowedStatus)) {

            $sql = "UPDATE tickets
                    SET status = :status
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':status' => $status,
                ':id' => $ticketId
            ]);

            header("Location: view-ticket.php?id=" . $ticketId);
            exit;
        }
    }
}

$sql = "SELECT comments.*, users.name AS user_name
        FROM comments
        INNER JOIN users ON users.id = comments.user_id
        WHERE comments.ticket_id = :ticket_id
        ORDER BY comments.created_at ASC";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':ticket_id' => $ticketId
]);

$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Chamado #<?= htmlspecialchars($ticket['id']) ?></title>
</head>
<body>

    <h1>Chamado #<?= htmlspecialchars($ticket['id']) ?></h1>

    <p>
        <strong>Título:</strong>
        <?= htmlspecialchars($ticket['title']) ?>
    </p>

    <p>
        <strong>Descrição:</strong>
        <br>
        <?= nl2br(htmlspecialchars($ticket['description'])) ?>
    </p>

    <p>
        <strong>Categoria:</strong>
        <?= htmlspecialchars($ticket['category']) ?>
    </p>

    <p>
        <strong>Prioridade:</strong>
        <?= htmlspecialchars($ticket['priority']) ?>
    </p>

    <p>
        <strong>Status:</strong>
        <?= htmlspecialchars($ticket['status']) ?>
    </p>

    <p>
        <strong>Aberto por:</strong>
        <?= htmlspecialchars($ticket['user_name']) ?>
    </p>

    <p>
        <strong>Data:</strong>
        <?= htmlspecialchars($ticket['created_at']) ?>
    </p>

    <?php if ($canManageTicket): ?>

        <hr>

        <h2>Alterar status</h2>

        <form method="POST">

            <select name="status" required>

                <option value="aberto"
                    <?= $ticket['status'] === 'aberto' ? 'selected' : '' ?>>
                    Aberto
                </option>

                <option value="em andamento"
                    <?= $ticket['status'] === 'em andamento' ? 'selected' : '' ?>>
                    Em andamento
                </option>

                <option value="finalizado"
                    <?= $ticket['status'] === 'finalizado' ? 'selected' : '' ?>>
                    Finalizado
                </option>

            </select>

            <button type="submit">
                Atualizar status
            </button>

        </form>

    <?php endif; ?>

    <hr>

    <h2>Comentários</h2>

    <?php if (empty($comments)): ?>

        <p>Nenhum comentário ainda.</p>

    <?php else: ?>

        <?php foreach ($comments as $comment): ?>

            <div style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">

                <strong>
                    <?= htmlspecialchars($comment['user_name']) ?>
                </strong>

                <small>
                    <?= htmlspecialchars($comment['created_at']) ?>
                </small>

                <p>
                    <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <hr>

    <h2>Adicionar comentário</h2>

    <form method="POST">

        <textarea
            name="comment"
            rows="4"
            cols="50"
            required
        ></textarea>

        <br><br>

        <button type="submit">
            Enviar comentário
        </button>

    </form>

    <br>

    <a href="dashboard.php">
        Voltar para dashboard
    </a>

</body>
</html>