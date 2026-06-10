<?php
require __DIR__ . '/../../core/middleware.php';
require_login();

$user_id = user_id();

function redirect_clients(string $params = ''): void {
    $target = route_url('clients');
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

    if ($action === 'create_client') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO clients (user_id, name, is_active) VALUES (?, ?, 1)');
            $stmt->execute([$user_id, $name]);
            redirect_clients('?created=1');
        }
        redirect_clients();
    }

    if ($action === 'toggle_client') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE clients SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);
        redirect_clients('?updated=1');
    }
}

$stmt = $pdo->prepare('SELECT id, name, is_active FROM clients WHERE user_id = ? ORDER BY is_active DESC, name');
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

render_layout_header();
?>

<h1 class="mb-4">Clients</h1>

<?php if (!empty($_GET['created']) || !empty($_GET['updated'])): ?>
    <div class="alert alert-success">Changes saved successfully.</div>
<?php endif; ?>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Add Client</h2>
    <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_client">
        <div class="col-md-9">
            <input type="text" name="name" class="form-control" placeholder="Client name" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Add Client</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <h2 class="h5 mb-3">All Clients</h2>
    <?php if ($clients): ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th style="width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= htmlspecialchars($client['name']) ?></td>
                            <td><?= (int)$client['is_active'] === 1 ? '<span class="badge bg-primary">Active</span>' : '<span class="badge bg-secondary">Archived</span>' ?></td>
                            <td>
                                <a href="<?= h(route_url('client_edit', ['id' => (int)$client['id']])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_client">
                                    <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= (int)$client['is_active'] === 1 ? 'btn-outline-secondary' : 'btn-outline-secondary' ?>">
                                        <?= (int)$client['is_active'] === 1 ? 'Archive' : 'Restore' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted mb-0">No clients yet.</p>
    <?php endif; ?>
</div>

<?php render_layout_footer(); ?>
