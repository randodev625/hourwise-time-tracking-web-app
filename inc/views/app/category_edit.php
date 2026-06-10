<?php
require __DIR__ . '/../../core/middleware.php';
require_login();

$user_id = user_id();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to_route('categories');
}

$stmt = $pdo->prepare('SELECT id, name, is_active FROM work_categories WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user_id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    redirect_to_route('categories');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            $recordsCountStmt = $pdo->prepare('SELECT COUNT(*) FROM time_records WHERE user_id = ? AND category_id = ?');
            $recordsCountStmt->execute([$user_id, $id]);
            $recordsCount = (int)$recordsCountStmt->fetchColumn();

            if ($recordsCount > 0) {
                $error = 'Cannot delete this category while it still has time entries.';
            } else {
                $delete = $pdo->prepare('DELETE FROM work_categories WHERE id = ? AND user_id = ?');
                $delete->execute([$id, $user_id]);
                redirect_to_route('categories', ['deleted' => '1']);
            }
        } else {
            $name = trim($_POST['name'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                $error = 'Category name is required.';
            } else {
                $update = $pdo->prepare('UPDATE work_categories SET name = ?, is_active = ? WHERE id = ? AND user_id = ?');
                $update->execute([$name, $is_active, $id, $user_id]);
                redirect_to_route('categories', ['updated' => '1']);
            }

            $category['name'] = $name;
            $category['is_active'] = $is_active;
        }
    }
}

render_layout_header();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Edit Category</h1>
    <a href="<?= h(route_url('categories')) ?>" class="btn btn-outline-secondary btn-sm">Back to Project Categories</a>
</div>

<div class="card p-3">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">

        <div class="col-12">
            <label for="name" class="form-label">Category name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$category['is_active'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>

        <div class="col-12 d-flex gap-2 justify-content-between">
            <div>
                <button type="submit" name="action" value="save" class="btn btn-primary">Save Changes</button>
                <a href="<?= h(route_url('categories')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
            <button type="submit" name="action" value="delete" class="btn btn-outline-danger" onclick="return confirm('Delete this category? This cannot be undone.');">Delete Category</button>
        </div>
    </form>
</div>

<?php render_layout_footer(); ?>
