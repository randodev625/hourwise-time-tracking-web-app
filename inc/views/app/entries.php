<?php
require __DIR__ . '/../../core/middleware.php';
require_login();

$user_id = user_id();
$displayTz = user_timezone_object();

$filters = [
    'user_id' => $user_id,
    'project_name' => trim($_REQUEST['project_name'] ?? ''),
    'client_name' => trim($_REQUEST['client_name'] ?? ''),
    'category_name' => trim($_REQUEST['category_name'] ?? ''),
    'start_date' => $_REQUEST['start_date'] ?? null,
    'end_date' => $_REQUEST['end_date'] ?? null,
];

function entries_redirect_with_filters(array $filters, string $extra = ''): void
{
    $query = array_filter([
        'project_name' => $filters['project_name'] ?? '',
        'client_name' => $filters['client_name'] ?? '',
        'category_name' => $filters['category_name'] ?? '',
        'start_date' => $filters['start_date'] ?? '',
        'end_date' => $filters['end_date'] ?? '',
    ], static fn($value) => $value !== null && $value !== '');

    if ($extra !== '') {
        parse_str($extra, $extraQuery);
        $query = array_merge($query, $extraQuery);
    }

    redirect_to_route('entries', $query);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_check()) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }

    $action = $_POST['action'];

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM time_records WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        entries_redirect_with_filters($filters, 'deleted=1');
    }

    if ($action === 'export') {
        $sql = 'SELECT
                    tr.*,
                    p.name AS project_name,
                    cl.name AS client_name,
                    wc.name AS category_name,
                    j.name AS legacy_job_name
                FROM time_records tr
                LEFT JOIN projects p ON p.id = tr.project_id
                LEFT JOIN clients cl ON cl.id = p.client_id
                LEFT JOIN work_categories wc ON wc.id = tr.category_id
                LEFT JOIN jobs j ON j.id = tr.job_id
                WHERE tr.user_id = :user_id';

        $params = [':user_id' => $user_id];

        if (!empty($filters['project_name'])) {
            $sql .= ' AND p.name LIKE :project_name';
            $params[':project_name'] = '%' . $filters['project_name'] . '%';
        }
        if (!empty($filters['client_name'])) {
            $sql .= ' AND cl.name LIKE :client_name';
            $params[':client_name'] = '%' . $filters['client_name'] . '%';
        }
        if (!empty($filters['category_name'])) {
            $sql .= ' AND wc.name LIKE :category_name';
            $params[':category_name'] = '%' . $filters['category_name'] . '%';
        }
        if (!empty($filters['start_date'])) {
            $sql .= ' AND tr.start_time >= :start_date';
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $sql .= ' AND tr.start_time <= :end_date';
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $exportScope = $_POST['export_scope'] ?? 'filtered';
        if ($exportScope === 'visible') {
            $visibleIdsRaw = trim($_POST['visible_ids'] ?? '');
            $visibleIds = $visibleIdsRaw !== ''
                ? array_values(array_filter(array_map('intval', explode(',', $visibleIdsRaw))))
                : [];

            if (!empty($visibleIds)) {
                $placeholders = [];
                foreach ($visibleIds as $index => $visibleId) {
                    $placeholder = ':visible_id_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $visibleId;
                }
                $sql .= ' AND tr.id IN (' . implode(',', $placeholders) . ')';
            } else {
                $sql .= ' AND 1 = 0';
            }
        }

        $sql .= ' ORDER BY tr.start_time DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries_csv = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filenameBase = !empty($entries_csv)
            ? sanitizeFilename(entry_project_label($entries_csv[0]))
            : 'time_entries';

        $scopeLabel = $exportScope === 'visible' ? 'visible' : 'filtered';
        $filename = $filenameBase . '_' . $scopeLabel . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Client', 'Project', 'Category', 'Date', 'Start Time', 'End Time', 'Duration (hours)', 'Comment']);

        foreach ($entries_csv as $entry) {
            $startDt = new DateTime($entry['start_time'], new DateTimeZone('UTC'));
            $startDt->setTimezone($displayTz);

            $endDt = null;
            if (!empty($entry['end_time'])) {
                $endDt = new DateTime($entry['end_time'], new DateTimeZone('UTC'));
                $endDt->setTimezone($displayTz);
            }

            fputcsv($output, [
                $entry['client_name'] ?? '',
                $entry['project_name'] ?? ($entry['legacy_job_name'] ?? ''),
                $entry['category_name'] ?? '',
                $startDt->format('m/d/Y'),
                $startDt->format('H:i:s'),
                $endDt ? $endDt->format('H:i:s') : '',
                $entry['duration'] !== null ? round($entry['duration'] / 3600, 2) : '',
                $entry['comment'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }
}

$limit = 15;
$offset = (int)($_GET['offset'] ?? 0);
$filters['limit'] = $limit;
$filters['offset'] = $offset;
[$sql, $params] = buildTimeRecordsQuery($filters);
$entries = executeQuery($sql, $params);

$returnQuery = http_build_query(array_filter([
    'project_name' => $filters['project_name'] ?? '',
    'client_name' => $filters['client_name'] ?? '',
    'category_name' => $filters['category_name'] ?? '',
    'start_date' => $filters['start_date'] ?? '',
    'end_date' => $filters['end_date'] ?? '',
], static fn($value) => $value !== null && $value !== ''));
$returnTo = route_url('entries') . ($returnQuery !== '' ? '?' . $returnQuery : '');

render_layout_header();
?>

<h1 class="mb-4">Manage Time Entries</h1>
<?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success">Entry updated successfully.</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success">Entry deleted successfully.</div>
<?php endif; ?>

<form method="get" class="mb-3" id="entries_filter_form">
    <div class="row g-2 align-items-end mb-2">
        <div class="col-md-2 position-relative">
            <label for="client_name" class="form-label">Client</label>
            <input type="text" id="client_name" name="client_name" class="form-control"
                placeholder="Search clients..."
                value="<?= htmlspecialchars($filters['client_name']) ?>" autocomplete="off">
            <div id="client_suggestions" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1000;"></div>
        </div>
        <div class="col-md-3 position-relative">
            <label for="project_name" class="form-label">Project</label>
            <input type="text" id="project_name" name="project_name" class="form-control"
                placeholder="Search projects..."
                value="<?= htmlspecialchars($filters['project_name']) ?>" autocomplete="off">
            <div id="project_suggestions" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1000;"></div>
        </div>
        <div class="col-md-3 position-relative">
            <label for="category_name" class="form-label">Category</label>
            <input type="text" id="category_name" name="category_name" class="form-control"
                placeholder="Search categories..."
                value="<?= htmlspecialchars($filters['category_name']) ?>" autocomplete="off">
            <div id="category_suggestions" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1000;"></div>
        </div>
        <div class="col-md-2">
            <label for="start_date" class="form-label">Start</label>
            <input type="date" id="start_date" name="start_date" class="form-control"
                value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label for="end_date" class="form-label">End</label>
            <input type="date" id="end_date" name="end_date" class="form-control"
                value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
        </div>
    </div>
    <div class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
        <div class="col-auto d-flex gap-1 flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_7days">Last 7 Days</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_30days">Last 30 Days</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_year">Calendar Year</button>
        </div>
    </div>
</form>

<form method="post" class="mb-3 d-flex gap-2 flex-wrap" id="export_form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="project_name" id="export_project_name" value="<?= htmlspecialchars($filters['project_name']) ?>">
    <input type="hidden" name="client_name" id="export_client_name" value="<?= htmlspecialchars($filters['client_name']) ?>">
    <input type="hidden" name="category_name" id="export_category_name" value="<?= htmlspecialchars($filters['category_name']) ?>">
    <input type="hidden" name="start_date" id="export_start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
    <input type="hidden" name="end_date" id="export_end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
    <input type="hidden" name="visible_ids" id="export_visible_ids" value="">
    <input type="hidden" name="export_scope" id="export_scope" value="filtered">

    <button type="submit" name="action" value="export" class="btn btn-outline-dark btn-sm" data-export-scope="visible">
        Export Visible Rows
    </button>
    <button type="submit" name="action" value="export" class="btn btn-outline-dark btn-sm" data-export-scope="filtered">
        Export All Matching Rows
    </button>
</form>

<div class="table-responsive">
    <table id="entries_table" class="table table-bordered table-striped align-middle">
        <colgroup>
            <col class="col-project">
            <col class="col-category">
            <col class="col-start">
            <col class="col-end">
            <col class="col-duration">
            <col class="col-comment">
            <col class="col-actions">
        </colgroup>
        <thead>
            <tr>
                <th>Project</th>
                <th>Category</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
                <th>Comment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="entries_tbody">
            <?php foreach ($entries as $e): ?>
                <tr data-entry-id="<?= (int)$e['id'] ?>">
                    <td><?= htmlspecialchars(entry_project_label($e)) ?></td>
                    <td><?= htmlspecialchars(entry_category_label($e)) ?></td>
                    <td><?= htmlspecialchars(formatLocalTimeRecentEntries($e['start_time'])) ?></td>
                    <td><?= $e['end_time'] ? htmlspecialchars(formatLocalTimeRecentEntries($e['end_time'])) : '—' ?></td>
                    <td>
                        <?php if ($e['duration'] === null): ?>
                            <?php $ts = (new DateTime($e['start_time'], new DateTimeZone('UTC')))->setTimezone($displayTz)->getTimestamp(); ?>
                            <span class="timer badge bg-primary" data-start-ts="<?= $ts ?>">0:00</span>
                        <?php else: ?>
                            <?= fmt_dur($e['duration']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="comment-cell"><?= $e['comment'] !== null && $e['comment'] !== '' ? nl2br(htmlspecialchars($e['comment'])) : '<span class="text-muted">No comment</span>' ?></td>
                    <td>
                        <a href="<?= h(route_url('entry_edit', ['id' => (int)$e['id'], 'return' => $returnTo])) ?>" class="btn btn-sm btn-outline-primary mb-1">Edit</a>
                        <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                        <?php /* <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this entry?');">Delete</button> --> */ ?>
                    </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="d-grid">
    <button id="load_more" class="btn btn-outline-primary btn-sm">Load More</button>
</div>

<?php render_layout_footer(); ?>
