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
                    (:ticket_id, :user_id, :comment)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':user_id' => $userId,
                ':comment' => $comment
            ]);

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

                    if (
                        move_uploaded_file(
                            $_FILES['attachment']['tmp_name'],
                            $uploadPath
                        )
                    ) {
                        $sql = "INSERT INTO attachments
                                (
                                    ticket_id,
                                    user_id,
                                    file_name,
                                    original_name
                                )
                                VALUES
                                (
                                    :ticket_id,
                                    :user_id,
                                    :file_name,
                                    :original_name
                                )";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            ':ticket_id' => $ticketId,
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
        ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':ticket_id' => $ticketId
]);

$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$formattedTicketDate = (new DateTime($ticket['created_at']))
    ->format('d/m/Y H:i');

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Chamado #<?= htmlspecialchars($ticket['id']) ?></title>
</head>
<body>

    <h1>Chamado #<?= htmlspecialchars($ticket['id']) ?></h1>

    <?php if (!empty($errorMessage)): ?>
        <p style="color: red;">
            <?= htmlspecialchars($errorMessage) ?>
        </p>
    <?php endif; ?>

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
        <?= htmlspecialchars($formattedTicketDate) ?>
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

    <h2>Anexos</h2>

    <?php if (empty($attachments)): ?>

        <p>Nenhum anexo enviado.</p>

    <?php else: ?>

        <ul>
            <?php foreach ($attachments as $attachment): ?>

                <li>
                    <a
                        href="attachment.php?id=<?= htmlspecialchars($attachment['id']) ?>"
                        target="_blank"
                    >
                        <?= htmlspecialchars($attachment['original_name']) ?>
                    </a>
                </li>

            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

    <hr>

    <h2>Comentários</h2>

    <?php if (empty($comments)): ?>

        <p>Nenhum comentário ainda.</p>

    <?php else: ?>

        <?php foreach ($comments as $comment): ?>

            <?php
                $commentUserName = $comment['user_name'];

                if ($comment['user_role'] === 'ti') {
                    $commentUserName .= ' - [TI]';
                }

                if ($comment['user_role'] === 'admin') {
                    $commentUserName .= ' - [ADMIN]';
                }

                $formattedCommentDate = (new DateTime($comment['created_at']))
                    ->format('d/m/Y H:i');
            ?>

            <div style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">

                <strong>
                    <?= htmlspecialchars($commentUserName) ?>
                </strong>

                <small>
                    <?= htmlspecialchars($formattedCommentDate) ?>
                </small>

                <p>
                    <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <hr>

    <h2>Adicionar comentário</h2>

    <?php if ($isTicketFinalized): ?>

        <p>Este chamado foi finalizado. Não é possível enviar novos comentários.</p>

    <?php else: ?>

        <form method="POST" enctype="multipart/form-data">

            <textarea
                name="comment"
                rows="4"
                cols="50"
                required
            ></textarea>

            <br><br>

            <label>Anexo:</label>
            <br>
            <input
                type="file"
                name="attachment"
                accept=".jpg,.jpeg,.png,.pdf,.txt"
            >

            <br><br>

            <small>
                Formatos permitidos: JPG, PNG, PDF e TXT. Tamanho máximo: 5MB.
            </small>

            <br><br>

            <button type="submit">
                Enviar comentário
            </button>

        </form>

    <?php endif; ?>

    <br>

    <a href="dashboard.php">
        Voltar para dashboard
    </a>

</body>
</html>