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
$errorMessage = "";

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
$isTicketFinalized = $ticket['status'] === 'finalizado';

if (!$isTicketOwner && !$canManageTicket) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['comment']) && !$isTicketFinalized) {

        $comment = trim($_POST['comment']);

        if (!empty($comment)) {

            $hasUploadError = false;

            $sql = "INSERT INTO comments 
                    (ticket_id, user_id, comment)
                    VALUES 
                    (:ticket_id, :user_id, :comment)
                    RETURNING id";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':user_id' => $userId,
                ':comment' => $comment
            ]);

            $commentId = $stmt->fetchColumn();

            if (
                isset($_FILES['attachment']) &&
                $_FILES['attachment']['error'] === UPLOAD_ERR_OK
            ) {
                $allowedFiles = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'pdf' => 'application/pdf',
                    'txt' => 'text/plain'
                ];

                $maxFileSize = 5 * 1024 * 1024;

                $originalName = $_FILES['attachment']['name'];
                $fileSize = $_FILES['attachment']['size'];

                $extension = strtolower(
                    pathinfo($originalName, PATHINFO_EXTENSION)
                );

                $fileMimeType = mime_content_type($_FILES['attachment']['tmp_name']);

                if (
                    array_key_exists($extension, $allowedFiles) &&
                    $allowedFiles[$extension] === $fileMimeType &&
                    $fileSize <= $maxFileSize
                ) {
                    $newFileName = uniqid('', true) . '.' . $extension;

                    $uploadDir = __DIR__ . '/../storage/uploads/';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $uploadPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {

                        $sql = "INSERT INTO attachments
                                (
                                    ticket_id,
                                    comment_id,
                                    user_id,
                                    file_name,
                                    original_name
                                )
                                VALUES
                                (
                                    :ticket_id,
                                    :comment_id,
                                    :user_id,
                                    :file_name,
                                    :original_name
                                )";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            ':ticket_id' => $ticketId,
                            ':comment_id' => $commentId,
                            ':user_id' => $userId,
                            ':file_name' => $newFileName,
                            ':original_name' => $originalName
                        ]);
                    } else {
                        $hasUploadError = true;
                        $errorMessage = "Erro ao salvar o arquivo enviado.";
                    }

                } else {
                    $hasUploadError = true;
                    $errorMessage = "Arquivo inválido. Envie JPG, PNG, PDF ou TXT com no máximo 5MB.";
                }
            }

            if (!$hasUploadError) {
                header("Location: view-ticket.php?id=" . $ticketId);
                exit;
            }
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

$sql = "SELECT comments.*, users.name AS user_name, users.role AS user_role
        FROM comments
        INNER JOIN users ON users.id = comments.user_id
        WHERE comments.ticket_id = :ticket_id
        ORDER BY comments.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':ticket_id' => $ticketId
]);

$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT *
        FROM attachments
        WHERE ticket_id = :ticket_id
        ORDER BY created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':ticket_id' => $ticketId
]);

$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attachmentsByComment = [];

foreach ($attachments as $attachment) {
    if (!empty($attachment['comment_id'])) {
        $attachmentsByComment[$attachment['comment_id']][] = $attachment;
    }
}

$formattedTicketDate = (new DateTime($ticket['created_at']))
    ->format('d/m/Y H:i');

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

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Chamado #<?= htmlspecialchars($ticket['id']) ?></title>

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

<div class="container mt-4 mb-5">

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h1 class="h3 mb-1">
                        Chamado #<?= htmlspecialchars($ticket['id']) ?>
                    </h1>

                    <p class="text-muted mb-0">
                        Aberto por <?= htmlspecialchars($ticket['user_name']) ?>
                        em <?= htmlspecialchars($formattedTicketDate) ?>
                    </p>
                </div>

                <div>
                    <span class="badge bg-<?= $statusClass ?>">
                        <?= htmlspecialchars($ticket['status']) ?>
                    </span>

                    <span class="badge bg-<?= $priorityClass ?>">
                        <?= htmlspecialchars($ticket['priority']) ?>
                    </span>
                </div>
            </div>

            <h5>
                <?= htmlspecialchars($ticket['title']) ?>
            </h5>

            <p class="mb-3">
                <?= nl2br(htmlspecialchars($ticket['description'])) ?>
            </p>

            <p class="mb-0">
                <strong>Categoria:</strong>
                <?= htmlspecialchars($ticket['category']) ?>
            </p>

        </div>
    </div>

    <?php if ($canManageTicket): ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">

                <h2 class="h5 mb-3">
                    Alterar status
                </h2>

                <form method="POST" class="d-flex gap-2">

                    <select name="status" class="form-select w-auto" required>

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

                    <button type="submit" class="btn btn-dark">
                        Atualizar
                    </button>

                </form>

            </div>
        </div>

    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <h2 class="h5 mb-3">
                Comentários
            </h2>

            <?php if (empty($comments)): ?>

                <p class="text-muted mb-0">
                    Nenhum comentário ainda.
                </p>

            <?php else: ?>

                <?php foreach ($comments as $comment): ?>

                    <?php
                        $commentUserName = $comment['user_name'];

                        $roleBadge = '';

                        if ($comment['user_role'] === 'ti') {
                            $roleBadge = '<span class="badge bg-info text-dark ms-1">TI</span>';
                        }

                        if ($comment['user_role'] === 'admin') {
                            $roleBadge = '<span class="badge bg-primary ms-1">ADMIN</span>';
                        }

                        $formattedCommentDate = (new DateTime($comment['created_at']))
                            ->format('d/m/Y H:i');

                        $commentAttachments = $attachmentsByComment[$comment['id']] ?? [];
                    ?>

                    <div class="border rounded p-3 mb-3 bg-white">

                        <div class="d-flex justify-content-between mb-2">

                            <div>
                                <strong>
                                    <?= htmlspecialchars($commentUserName) ?>
                                </strong>

                                <?= $roleBadge ?>
                            </div>

                            <small class="text-muted">
                                <?= htmlspecialchars($formattedCommentDate) ?>
                            </small>

                        </div>

                        <p class="mb-2">
                            <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                        </p>

                        <?php if (!empty($commentAttachments)): ?>

                            <div class="mt-3 pt-2 border-top">

                                <small class="text-muted d-block mb-2">
                                    Anexo:
                                </small>

                                <?php foreach ($commentAttachments as $attachment): ?>

                                    <a
                                        href="attachment.php?id=<?= htmlspecialchars($attachment['id']) ?>"
                                        target="_blank"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        📎 <?= htmlspecialchars($attachment['original_name']) ?>
                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            <h2 class="h5 mb-3">
                Adicionar comentário
            </h2>

            <?php if ($isTicketFinalized): ?>

                <div class="alert alert-secondary mb-0">
                    Este chamado foi finalizado. Não é possível enviar novos comentários.
                </div>

            <?php else: ?>

                <form method="POST" enctype="multipart/form-data">

                    <div class="mb-3">
                        <textarea
                            name="comment"
                            class="form-control"
                            rows="4"
                            placeholder="Digite sua resposta..."
                            required
                        ></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Anexo opcional
                        </label>

                        <input
                            type="file"
                            name="attachment"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.pdf,.txt"
                        >

                        <div class="form-text">
                            Formatos permitidos: JPG, PNG, PDF e TXT. Tamanho máximo: 5MB.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Enviar comentário
                    </button>

                </form>

            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>