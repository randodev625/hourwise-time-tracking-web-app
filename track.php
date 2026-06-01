<?php
require __DIR__ . '/middleware.php';
require_login();

$user_id = user_id();

$projectsStmt = $pdo->prepare('
    SELECT p.id, p.name AS project_name, c.name AS client_name
    FROM projects p
    JOIN clients c ON c.id = p.client_id
    WHERE p.user_id = ? AND p.is_active = 1
    ORDER BY c.name, p.name
');
$projectsStmt->execute([$user_id]);
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesStmt = $pdo->prepare('
    SELECT id, name
    FROM work_categories
    WHERE user_id = ? AND is_active = 1
    ORDER BY name
');
$categoriesStmt->execute([$user_id]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['start_timer'])) {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);

        $projectStmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
        $projectStmt->execute([$project_id, $user_id]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

        $categoryStmt = $pdo->prepare('SELECT id FROM work_categories WHERE id = ? AND user_id = ? LIMIT 1');
        $categoryStmt->execute([$category_id, $user_id]);
        $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

        if ($project && $category) {
            $pdo->prepare('
                UPDATE time_records
                SET duration = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                    end_time = NOW()
                WHERE user_id = ? AND duration IS NULL
            ')->execute([$user_id]);

            $pdo->prepare('
                INSERT INTO time_records (user_id, job_id, project_id, category_id, start_time)
                VALUES (?, NULL, ?, ?, NOW())
            ')->execute([$user_id, $project_id, $category_id]);
        }

        header('Location: track.php');
        exit;
    }

    if (!empty($_POST['stop_timer'])) {
        $record_id = (int)$_POST['stop_timer'];
        $returnTo = trim($_POST['return_to'] ?? 'track.php');
        if ($returnTo === '') {
            $returnTo = 'track.php';
        }

        $pdo->prepare('
            UPDATE time_records
            SET duration = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                end_time = NOW()
            WHERE id = ? AND user_id = ? AND duration IS NULL
        ')->execute([$record_id, $user_id]);

        header('Location: entry_edit.php?id=' . $record_id . '&stopped=1&return=' . urlencode($returnTo));
        exit;
    }
}

$runningStmt = $pdo->prepare('
    SELECT tr.id, tr.start_time, p.name AS project_name, c.name AS client_name, wc.name AS category_name
    FROM time_records tr
    LEFT JOIN projects p ON p.id = tr.project_id
    LEFT JOIN clients c ON c.id = p.client_id
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.user_id = ? AND tr.duration IS NULL
    ORDER BY tr.start_time DESC
');
$runningStmt->execute([$user_id]);
$running = $runningStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Track Time';
include __DIR__ . '/header.php';
?>

<h1 class="mb-4">Track Time</h1>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Running Timers</h2>
    <?php if (!empty($running)): ?>
        <ul class="list-group">
            <?php foreach ($running as $r): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <?= htmlspecialchars(($r['client_name'] ? $r['client_name'] . ' — ' : '') . ($r['project_name'] ?? 'Untitled Project')) ?>
                        <span class="text-muted">/ <?= htmlspecialchars($r['category_name'] ?? 'No Category') ?></span>
                        <?php
                        $dt = new DateTime($r['start_time'], new DateTimeZone('UTC'));
                        $dt->setTimezone(user_timezone_object());
                        $ts = $dt->getTimestamp();
                        ?>
                        <span class="timer badge bg-primary ms-2" data-start-ts="<?= $ts ?>">0:00</span>
                    </span>
                    <form method="post" class="m-0">
                        <input type="hidden" name="return_to" value="track.php">
                        <button type="submit" name="stop_timer" value="<?= $r['id'] ?>" class="btn btn-danger btn-sm">Stop</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><em>No timers running.</em></p>
    <?php endif; ?>
</div>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Start a New Timer</h2>
    <form method="post" class="row g-2">
        <div class="col-md-6">
            <label for="project_id" class="form-label">Project</label>
            <select id="project_id" name="project_id" class="form-select" required>
                <?php foreach ($projects as $i => $project): ?>
                    <option value="<?= $project['id'] ?>" <?= $i === 0 ? 'selected' : '' ?>>
                        <?= htmlspecialchars($project['client_name'] . ' — ' . $project['project_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="category_id" class="form-label">Category</label>
            <select id="category_id" name="category_id" class="form-select" required>
                <?php foreach ($categories as $i => $category): ?>
                    <option value="<?= $category['id'] ?>" <?= $i === 0 ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" name="start_timer" value="1" class="btn btn-primary w-100">Start</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
