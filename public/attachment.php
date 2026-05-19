<?php

session_start();

require_once __DIR__ . '/../config/Connection.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$attachmentId) {
    header('Location: dashboard.php');
    exit;
}

$pdo = Connection::connect();

$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role'];

$canManageTicket = in_array($userRole, ['ti', 'admin']);

$sql = "SELECT attachments.*, tickets.user_id AS ticket_owner_id
        FROM attachments
        INNER JOIN tickets ON tickets.id = attachments.ticket_id
        WHERE attachments.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':id' => $attachmentId
]);

$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    header('Location: dashboard.php');
    exit;
}

$isTicketOwner = ((int) $attachment['ticket_owner_id'] === (int) $userId);

if (!$isTicketOwner && !$canManageTicket) {
    header('Location: dashboard.php');
    exit;
}

$filePath = __DIR__ . '/../storage/uploads/' . $attachment['file_name'];

if (!file_exists($filePath)) {
    die('Arquivo não encontrado.');
}

$mimeType = mime_content_type($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $attachment['original_name'] . '"');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;