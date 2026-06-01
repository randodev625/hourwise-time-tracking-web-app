<?php
require __DIR__ . '/middleware.php';
require_login();

$user_id = user_id();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: projects.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, client_id, name, is_active FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: projects.php');
    exit;
}

$clientsStmt = $pdo->prepare('SELECT id, name, is_active FROM clients WHERE user_id = ? ORDER BY is_active DESC, name');
$clientsStmt->execute([$user_id]);
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $recordsCountStmt = $pdo->prepare('SELECT COUNT(*) FROM time_records WHERE user_id = ? AND project_id = ?');
        $recordsCountStmt->execute([$user_id, $id]);
        $recordsCount = (int)$recordsCountStmt->fetchColumn();

        if ($recordsCount > 0) {
            $error = 'Cannot delete this project while it still has time entries.';
        } else {
            $delete = $pdo->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
            $delete->execute([$id, $user_id]);
            header('Location: projects.php?deleted=1');
            exit;
        }
    } else {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $error = 'Project name is required.';
        } elseif ($client_id <= 0) {
            $error = 'Please choose a client.';
        } else {
            $clientCheck = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND user_id = ? LIMIT 1');
            $clientCheck->execute([$client_id, $user_id]);
            if (!$clientCheck->fetch()) {
                $error = 'Selected client is invalid.';
            } else {
                $update = $pdo->prepare('UPDATE projects SET client_id = ?, name = ?, is_active = ? WHERE id = ? AND user_id = ?');
                $update->execute([$client_id, $name, $is_active, $id, $user_id]);
                header('Location: projects.php?updated=1');
                exit;
            }
        }

        $project['client_id'] = $client_id;
        $project['name'] = $name;
        $project['is_active'] = $is_active;
    }
}

$page_title = 'Edit Project';
include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Edit Project</h1>
    <a href="projects.php" class="btn btn-outline-secondary btn-sm">Back to Projects</a>
</div>

<div class="card p-3">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <input type="hidden" name="id" value="<?= (int)$project['id'] ?>">

        <div class="col-md-6">
            <label for="client_id" class="form-label">Client</label>
            <select id="client_id" name="client_id" class="form-select" required>
                <option value="">Select client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int)$client['id'] ?>" <?= (int)$client['id'] === (int)$project['client_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['name']) ?><?= (int)$client['is_active'] === 0 ? ' (Archived)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="name" class="form-label">Project name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($project['name']) ?>" required>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$project['is_active'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>

        <div class="col-12 d-flex gap-2 justify-content-between">
            <div>
                <button type="submit" name="action" value="save" class="btn btn-primary">Save Changes</button>
                <a href="projects.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
            <button type="submit" name="action" value="delete" class="btn btn-outline-danger" onclick="return confirm('Delete this project? This cannot be undone.');">Delete Project</button>

        </div>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>