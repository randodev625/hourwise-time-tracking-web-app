<?php
require __DIR__ . '/middleware.php';
require_login();

$user_id = user_id();
$displayTz = user_timezone_object();
$utcTz = new DateTimeZone('UTC');

// For security, we only allow numeric IDs and validate them before querying the database.
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$returnTo = safe_redirect_path((string)($_GET['return'] ?? $_POST['return'] ?? 'entries.php'));
$stopped = isset($_GET['stopped']) && $_GET['stopped'] === '1';
if ($id <= 0) {
    header('Location: entries.php');
    exit;
}

// Fetch the entry to edit, ensuring it belongs to the current user. We also join related tables to get project, client, and category names for display.
$stmt = $pdo->prepare('
    SELECT tr.*, p.name AS project_name, cl.name AS client_name, wc.name AS category_name
    FROM time_records tr
    LEFT JOIN projects p ON p.id = tr.project_id
    LEFT JOIN clients cl ON cl.id = p.client_id
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.id = ? AND tr.user_id = ?
    LIMIT 1
');
$stmt->execute([$id, $user_id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    header('Location: entries.php');
    exit;
}

// Fetch all projects and categories for the dropdowns, ensuring they belong to the current user. We also order them by active status and name for better UX.
$projectsStmt = $pdo->prepare('
    SELECT p.id, p.name AS project_name, c.name AS client_name, p.is_active
    FROM projects p
    JOIN clients c ON c.id = p.client_id
    WHERE p.user_id = ?
    ORDER BY p.is_active DESC, c.name, p.name
');
$projectsStmt->execute([$user_id]);
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesStmt = $pdo->prepare('SELECT id, name, is_active FROM work_categories WHERE user_id = ? ORDER BY is_active DESC, name');
$categoriesStmt->execute([$user_id]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission. We validate the inputs, update the database, and redirect back to the entries list with a success message. If there are validation errors, we display them above the form.
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            $delete = $pdo->prepare('DELETE FROM time_records WHERE id = ? AND user_id = ? LIMIT 1');
            $delete->execute([$id, $user_id]);

            header('Location: ' . add_query_arg($returnTo, 'deleted', '1'));
            exit;
        }

        $project_id = (int)($_POST['project_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $startInput = $_POST['start_time'] ?? '';
        $endInput = $_POST['end_time'] ?? '';
        $comment = trim($_POST['comment'] ?? '');

        $projectCheck = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
        $projectCheck->execute([$project_id, $user_id]);

        $categoryCheck = $pdo->prepare('SELECT id FROM work_categories WHERE id = ? AND user_id = ? LIMIT 1');
        $categoryCheck->execute([$category_id, $user_id]);

        $startUtc = parse_local_datetime($startInput, $displayTz, $utcTz);
        $endUtc = parse_local_datetime($endInput, $displayTz, $utcTz);

        if (!$projectCheck->fetch()) {
            $error = 'Please select a valid project.';
        } elseif (!$categoryCheck->fetch()) {
            $error = 'Please select a valid category.';
        } elseif ($startUtc === null) {
            $error = 'Start time is required.';
        } elseif ($endInput !== '' && $endUtc === null) {
            $error = 'End time is invalid.';
        } elseif ($endUtc !== null && strtotime($endUtc) < strtotime($startUtc)) {
            $error = 'End time cannot be earlier than start time.';
        } else {
            $duration = $endUtc !== null ? (strtotime($endUtc) - strtotime($startUtc)) : null;

            $update = $pdo->prepare('
                UPDATE time_records
                SET project_id = :project_id,
                    category_id = :category_id,
                    job_id = NULL,
                    start_time = :start_time,
                    end_time = :end_time,
                    duration = :duration,
                    comment = :comment
                WHERE id = :id AND user_id = :user_id
            ');
            $update->execute([
                ':project_id' => $project_id,
                ':category_id' => $category_id,
                ':start_time' => $startUtc,
                ':end_time' => $endUtc,
                ':duration' => $duration,
                ':comment' => $comment !== '' ? $comment : null,
                ':id' => $id,
                ':user_id' => $user_id,
            ]);

            header('Location: ' . add_query_arg($returnTo, 'updated', '1'));
            exit;
        }

        $entry['project_id'] = $project_id;
        $entry['category_id'] = $category_id;
        $entry['start_time'] = $startUtc ?? $entry['start_time'];
        $entry['end_time'] = $endUtc;
        $entry['duration'] = $endUtc !== null && $startUtc !== null ? (strtotime($endUtc) - strtotime($startUtc)) : null;
        $entry['comment'] = $comment;
    }
}

$page_title = 'Edit Entry';
include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Edit Time Entry</h1>
    <a href="<?= htmlspecialchars($returnTo !== '' ? $returnTo : 'entries.php') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<div class="card p-3">
    <?php if ($stopped): ?>
        <div class="alert alert-info">Timer stopped. Add a note below before you continue.</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">

        <div class="col-md-6">
            <label for="project_id" class="form-label">Project</label>
            <select id="project_id" name="project_id" class="form-select" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int)$project['id'] ?>" <?= (int)$project['id'] === (int)($entry['project_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($project['client_name'] . ' — ' . $project['project_name']) ?><?= (int)$project['is_active'] === 0 ? ' (Archived)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="category_id" class="form-label">Category</label>
            <select id="category_id" name="category_id" class="form-select" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int)$category['id'] ?>" <?= (int)$category['id'] === (int)($entry['category_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?><?= (int)$category['is_active'] === 0 ? ' (Archived)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="start_time" class="form-label">Start time</label>
            <input type="datetime-local" id="start_time" name="start_time" class="form-control" value="<?= htmlspecialchars(formatLocalTime($entry['start_time'])) ?>" required>
        </div>

        <div class="col-md-6">
            <label for="end_time" class="form-label">End time</label>
            <input type="datetime-local" id="end_time" name="end_time" class="form-control" value="<?= htmlspecialchars(formatLocalTime($entry['end_time'])) ?>">
            <div class="form-text">Leave blank if the timer is still running.</div>
        </div>

        <div class="col-12">
            <label for="comment" class="form-label">Comment</label>
            <textarea id="comment" name="comment" rows="5" class="form-control" <?= $stopped ? 'autofocus' : '' ?>><?= htmlspecialchars($entry['comment'] ?? '') ?></textarea>
        </div>

        <div class="col-12 d-flex gap-2 flex-wrap juswtify-content-between">
            <div class="me-auto d-flex gap-2">
                <button type="submit" name="action" value="save" class="btn btn-primary">Save Changes</button>
                <a href="<?= htmlspecialchars($returnTo !== '' ? $returnTo : 'entries.php') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
            <button type="submit" name="action" value="delete" class="btn btn-outline-danger" formnovalidate onclick="return confirm('Delete this entry permanently?');">Delete Entry</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
