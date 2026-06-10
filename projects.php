<?php
require __DIR__ . '/middleware.php';
require_login();

$user_id = user_id();

function redirect_projects(string $params = ''): void {
    $target = route_url('projects');
    if ($params !== '') {
        $target .= $params;
    }
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_check()) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }

    $action = $_POST['action'];

    if ($action === 'create_project') {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($client_id > 0 && $name !== '') {
            $clientStmt = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND user_id = ? LIMIT 1');
            $clientStmt->execute([$client_id, $user_id]);
            if ($clientStmt->fetch()) {
                $stmt = $pdo->prepare('INSERT INTO projects (user_id, client_id, name, is_active) VALUES (?, ?, ?, 1)');
                $stmt->execute([$user_id, $client_id, $name]);
                redirect_projects('?created=1');
            }
        }
        redirect_projects();
    }

    if ($action === 'toggle_project') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE projects SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);
        redirect_projects('?updated=1');
    }
}

$clientsStmt = $pdo->prepare('SELECT id, name, is_active FROM clients WHERE user_id = ? ORDER BY is_active DESC, name');
$clientsStmt->execute([$user_id]);
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);
$activeClients = array_values(array_filter($clients, static fn($c) => (int)$c['is_active'] === 1));

$projectsStmt = $pdo->prepare('
    SELECT p.id, p.name, p.client_id, p.is_active, c.name AS client_name
    FROM projects p
    JOIN clients c ON c.id = p.client_id
    WHERE p.user_id = ?
    ORDER BY p.is_active DESC, c.name, p.name
');
$projectsStmt->execute([$user_id]);
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Projects';
include __DIR__ . '/header.php';
?>

<h1 class="mb-4">Projects</h1>

<?php if (!empty($_GET['created']) || !empty($_GET['updated'])): ?>
    <div class="alert alert-success">Changes saved successfully.</div>
<?php endif; ?>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Add Project</h2>
    <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_project">
        <div class="col-md-4">
            <label class="form-label">Client</label>
            <select name="client_id" class="form-select" required>
                <option value="">Select client</option>
                <?php foreach ($activeClients as $client): ?>
                    <option value="<?= (int)$client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">Project name</label>
            <input type="text" name="name" class="form-control" placeholder="Project name" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Add Project</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <h2 class="h5 mb-3">All Projects</h2>
    <?php if ($projects): ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th style="width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['client_name']) ?></td>
                            <td><?= htmlspecialchars($project['name']) ?></td>
                            <td><?= (int)$project['is_active'] === 1 ? '<span class="badge bg-primary">Active</span>' : '<span class="badge bg-secondary">Archived</span>' ?></td>
                            <td>
                                <a href="<?= h(route_url('project_edit', ['id' => (int)$project['id']])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_project">
                                    <input type="hidden" name="id" value="<?= (int)$project['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= (int)$project['is_active'] === 1 ? 'btn-outline-secondary' : 'btn-outline-secondary' ?>">
                                        <?= (int)$project['is_active'] === 1 ? 'Archive' : 'Restore' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted mb-0">No projects yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
