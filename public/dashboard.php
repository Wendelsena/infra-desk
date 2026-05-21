<?php

session_start();

require_once __DIR__ . '/../config/Connection.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

$pdo = Connection::connect();

$statusFilter = $_GET['status'] ?? '';
$slaFilter = $_GET['sla'] ?? '';
$orderFilter = $_GET['order'] ?? '';
$search = trim($_GET['search'] ?? '');

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;

$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

$allowedStatus = [
    'aberto',
    'em andamento',
    'finalizado'
];

if (!in_array($statusFilter, $allowedStatus)) {
    $statusFilter = '';
}

if ($slaFilter !== 'atrasado') {
    $slaFilter = '';
}

if ($orderFilter !== 'prioridade') {
    $orderFilter = '';
}

$conditions = [];
$params = [];

$baseSql = "FROM tickets
            INNER JOIN users ON users.id = tickets.user_id";

if (!in_array($user['role'], ['ti', 'admin'])) {
    $conditions[] = "tickets.user_id = :user_id";
    $params[':user_id'] = $user['id'];
}

if (!empty($statusFilter)) {
    $conditions[] = "tickets.status = :status";
    $params[':status'] = $statusFilter;
}

if ($slaFilter === 'atrasado') {
    $conditions[] = "tickets.due_at < NOW()";
    $conditions[] = "tickets.status != 'finalizado'";
}

if (!empty($search)) {
    $conditions[] = "(
        tickets.title ILIKE :search
        OR tickets.description ILIKE :search
        OR tickets.category ILIKE :search
        OR users.name ILIKE :search
    )";

    $params[':search'] = '%' . $search . '%';
}

$whereSql = "";

if (!empty($conditions)) {
    $whereSql = " WHERE " . implode(' AND ', $conditions);
}

$countSql = "SELECT COUNT(*) " . $baseSql . $whereSql;

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);

$totalTickets = (int) $stmt->fetchColumn();
$totalPages = (int) ceil($totalTickets / $itemsPerPage);

$statsSql = "SELECT
                COUNT(*) FILTER (WHERE tickets.status = 'aberto') AS open_count,
                COUNT(*) FILTER (WHERE tickets.status = 'em andamento') AS progress_count,
                COUNT(*) FILTER (WHERE tickets.status = 'finalizado') AS closed_count,
                COUNT(*) FILTER (
                    WHERE tickets.due_at < NOW()
                    AND tickets.status != 'finalizado'
                ) AS late_count
            " . $baseSql . $whereSql;

$stmt = $pdo->prepare($statsSql);
$stmt->execute($params);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$openCount = $stats['open_count'] ?? 0;
$progressCount = $stats['progress_count'] ?? 0;
$closedCount = $stats['closed_count'] ?? 0;
$lateCount = $stats['late_count'] ?? 0;

$sql = "SELECT tickets.*, users.name AS user_name
        " . $baseSql . $whereSql;

if ($orderFilter === 'prioridade') {
    $sql .= " ORDER BY
                CASE
                    WHEN tickets.due_at < NOW()
                    AND tickets.status != 'finalizado' THEN 0
                    WHEN tickets.priority = 'alta' THEN 1
                    WHEN tickets.priority = 'media' THEN 2
                    WHEN tickets.priority = 'baixa' THEN 3
                    ELSE 4
                END,
                tickets.created_at DESC";
} else {
    $sql .= " ORDER BY tickets.created_at DESC";
}

$sql .= " LIMIT " . $itemsPerPage . " OFFSET " . $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildQuery(array $newParams = []): string
{
    $currentParams = $_GET;

    foreach ($newParams as $key => $value) {
        if ($value === '' || $value === null) {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }

    return http_build_query($currentParams);
}

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

    <style>
        .sla-badge {
            cursor: default;
        }

        .tooltip {
            --bs-tooltip-bg: #212529;
            --bs-tooltip-color: #fff;
            --bs-tooltip-padding-x: 12px;
            --bs-tooltip-padding-y: 8px;
            --bs-tooltip-border-radius: 8px;
            --bs-tooltip-font-size: 13px;
            --bs-tooltip-opacity: 0.95;
        }
    </style>
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

<div class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h1 class="h3">
            Dashboard
        </h1>

        <a href="create-ticket.php" class="btn btn-primary">
            Novo chamado
        </a>

    </div>

    <div class="row mb-4">

        <div class="col-md-3">
            <div class="card border-warning shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Abertos</h5>
                    <h2><?= htmlspecialchars($openCount) ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-primary shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Em andamento</h5>
                    <h2><?= htmlspecialchars($progressCount) ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-success shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Finalizados</h5>
                    <h2><?= htmlspecialchars($closedCount) ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-danger shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Atrasados</h5>
                    <h2><?= htmlspecialchars($lateCount) ?></h2>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">

            <form method="GET" class="row g-3 align-items-center">

                <div class="col-md-9">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Pesquisar por título, descrição, categoria ou usuário..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>

                <?php if (!empty($statusFilter)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <?php endif; ?>

                <?php if (!empty($slaFilter)): ?>
                    <input type="hidden" name="sla" value="<?= htmlspecialchars($slaFilter) ?>">
                <?php endif; ?>

                <?php if (!empty($orderFilter)): ?>
                    <input type="hidden" name="order" value="<?= htmlspecialchars($orderFilter) ?>">
                <?php endif; ?>

                <div class="col-md-3 d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-dark">
                        Pesquisar
                    </button>

                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        Limpar
                    </a>
                </div>

            </form>

        </div>
    </div>

    <div class="mb-3 d-flex flex-wrap gap-2">

        <a
            href="dashboard.php?<?= buildQuery(['status' => '', 'sla' => '', 'page' => 1]) ?>"
            class="btn btn-sm <?= empty($statusFilter) && empty($slaFilter) ? 'btn-dark' : 'btn-outline-dark' ?>"
        >
            Todos
        </a>

        <a
            href="dashboard.php?<?= buildQuery(['status' => 'aberto', 'sla' => '', 'page' => 1]) ?>"
            class="btn btn-sm <?= $statusFilter === 'aberto' ? 'btn-warning' : 'btn-outline-warning' ?>"
        >
            Abertos
        </a>

        <a
            href="dashboard.php?<?= buildQuery(['status' => 'em andamento', 'sla' => '', 'page' => 1]) ?>"
            class="btn btn-sm <?= $statusFilter === 'em andamento' ? 'btn-primary' : 'btn-outline-primary' ?>"
        >
            Em andamento
        </a>

        <a
            href="dashboard.php?<?= buildQuery(['status' => 'finalizado', 'sla' => '', 'page' => 1]) ?>"
            class="btn btn-sm <?= $statusFilter === 'finalizado' ? 'btn-success' : 'btn-outline-success' ?>"
        >
            Finalizados
        </a>

        <a
            href="dashboard.php?<?= buildQuery(['sla' => 'atrasado', 'status' => '', 'page' => 1]) ?>"
            class="btn btn-sm <?= $slaFilter === 'atrasado' ? 'btn-danger' : 'btn-outline-danger' ?>"
        >
            Atrasados
        </a>

        <a
            href="dashboard.php?<?= buildQuery(['order' => 'prioridade', 'page' => 1]) ?>"
            class="btn btn-sm <?= $orderFilter === 'prioridade' ? 'btn-dark' : 'btn-outline-dark' ?>"
        >
            Prioridade
        </a>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-muted mb-0">
                    Exibindo <?= count($tickets) ?> de <?= htmlspecialchars($totalTickets) ?> chamado(s)
                </p>

                <p class="text-muted mb-0">
                    Página <?= htmlspecialchars($page) ?> de <?= htmlspecialchars(max($totalPages, 1)) ?>
                </p>
            </div>

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
                            <th>Vencimento</th>
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

                                $formattedDate = (new DateTime($ticket['created_at']))
                                    ->format('d/m/Y H:i');

                                $formattedDueDate = 'Sem prazo';

                                if (!empty($ticket['due_at'])) {
                                    $formattedDueDate = (new DateTime($ticket['due_at']))
                                        ->format('d/m/Y H:i');
                                }

                                $slaText = 'Sem prazo';
                                $slaClass = 'secondary';

                                if (!empty($ticket['due_at'])) {

                                    if ($ticket['status'] === 'finalizado') {
                                        $slaText = 'Finalizado';
                                        $slaClass = 'success';
                                    } else {
                                        $dueDate = new DateTime($ticket['due_at']);
                                        $now = new DateTime();

                                        $diffSeconds = $dueDate->getTimestamp() - $now->getTimestamp();
                                        $hoursRemaining = floor($diffSeconds / 3600);

                                        if ($diffSeconds <= 0) {
                                            $slaText = 'Atrasado';
                                            $slaClass = 'danger';
                                        } elseif ($hoursRemaining <= 4) {
                                            $slaText = 'Próximo do vencimento';
                                            $slaClass = 'warning';
                                        } else {
                                            $slaText = 'No prazo';
                                            $slaClass = 'success';
                                        }
                                    }
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
                                    <?= htmlspecialchars($formattedDate) ?>
                                </td>

                                <td>
                                    <span
                                        class="badge bg-<?= $slaClass ?> sla-badge"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="<?= htmlspecialchars($formattedDueDate) ?>"
                                    >
                                        <?= htmlspecialchars($slaText) ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="view-ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>"
                                        class="btn btn-sm btn-dark"
                                    >
                                        Ver
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

                <?php if ($totalPages > 1): ?>

                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="dashboard.php?<?= buildQuery(['page' => $page - 1]) ?>"
                                >
                                    Anterior
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                    <a
                                        class="page-link"
                                        href="dashboard.php?<?= buildQuery(['page' => $i]) ?>"
                                    >
                                        <?= $i ?>
                                    </a>
                                </li>

                            <?php endfor; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="dashboard.php?<?= buildQuery(['page' => $page + 1]) ?>"
                                >
                                    Próxima
                                </a>
                            </li>

                        </ul>
                    </nav>

                <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');

    tooltipTriggerList.forEach(function (tooltipTriggerElement) {
        new bootstrap.Tooltip(tooltipTriggerElement, {
            trigger: 'hover',
            delay: {
                show: 120,
                hide: 80
            }
        });
    });
</script>

</body>
</html>