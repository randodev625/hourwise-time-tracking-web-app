<?php
require __DIR__ . '/middleware.php';
require_login();

$user_id = user_id();

function redirect_categories(string $params = ''): void {
    header('Location: categories.php' . $params);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO work_categories (user_id, name, is_active) VALUES (?, ?, 1)');
            $stmt->execute([$user_id, $name]);
            redirect_categories('?created=1');
        }
        redirect_categories();
    }

    if ($action === 'toggle_category') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE work_categories SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);
        redirect_categories('?updated=1');
    }
}

$stmt = $pdo->prepare('SELECT id, name, is_active FROM work_categories WHERE user_id = ? ORDER BY is_active DESC, name');
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Project Categories';
include __DIR__ . '/header.php';
?>

<h1 class="mb-4">Project Categories</h1>

<?php if (!empty($_GET['created']) || !empty($_GET['updated'])): ?>
    <div class="alert alert-success">Changes saved successfully.</div>
<?php endif; ?>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Add Category</h2>
    <form method="post" class="row g-2">
        <input type="hidden" name="action" value="create_category">
        <div class="col-md-9">
            <input type="text" name="name" class="form-control" placeholder="Category name" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Add Category</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <h2 class="h5 mb-3">All Categories</h2>
    <?php if ($categories): ?>
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
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= htmlspecialchars($category['name']) ?></td>
                            <td><?= (int)$category['is_active'] === 1 ? '<span class="badge bg-primary">Active</span>' : '<span class="badge bg-secondary">Archived</span>' ?></td>
                            <td>
                                <a href="category_edit.php?id=<?= (int)$category['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_category">
                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= (int)$category['is_active'] === 1 ? 'btn-outline-secondary' : 'btn-outline-secondary' ?>">
                                        <?= (int)$category['is_active'] === 1 ? 'Archive' : 'Restore' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted mb-0">No categories yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
