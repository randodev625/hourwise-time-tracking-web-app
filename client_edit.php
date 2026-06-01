<?php
require __DIR__ . '/middleware.php';
require_login();

$user_id = user_id();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: clients.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, is_active FROM clients WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header('Location: clients.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $projectCountStmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE user_id = ? AND client_id = ?');
        $projectCountStmt->execute([$user_id, $id]);
        $projectCount = (int)$projectCountStmt->fetchColumn();

        if ($projectCount > 0) {
            $error = 'Cannot delete this client while it still has projects.';
        } else {
            $delete = $pdo->prepare('DELETE FROM clients WHERE id = ? AND user_id = ?');
            $delete->execute([$id, $user_id]);
            header('Location: clients.php?deleted=1');
            exit;
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $error = 'Client name is required.';
        } else {
            $update = $pdo->prepare('UPDATE clients SET name = ?, is_active = ? WHERE id = ? AND user_id = ?');
            $update->execute([$name, $is_active, $id, $user_id]);
            header('Location: clients.php?updated=1');
            exit;
        }

        $client['name'] = $name;
        $client['is_active'] = $is_active;
    }
}

$page_title = 'Edit Client';
include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Edit Client</h1>
    <a href="clients.php" class="btn btn-outline-secondary btn-sm">Back to Clients</a>
</div>

<div class="card p-3">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">

        <div class="col-12">
            <label for="name" class="form-label">Client name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($client['name']) ?>" required>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$client['is_active'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>

        <div class="col-12 d-flex gap-2 justify-content-between">
            <div>
                <button type="submit" name="action" value="save" class="btn btn-primary">Save Changes</button>
                <a href="clients.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
            <button type="submit" name="action" value="delete" class="btn btn-outline-danger" onclick="return confirm('Delete this client? This cannot be undone.');">Delete Client</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>