<?php
require __DIR__ . '/middleware.php';
require_login();

$displayTz = user_timezone_object();

$limit  = 15;
$filters = [
    'user_id' => user_id(),
    'offset' => (int)($_GET['offset'] ?? 0),
    'limit' => $limit,
    'project_name' => trim($_GET['project_name'] ?? ''),
    'client_name' => trim($_GET['client_name'] ?? ''),
    'category_name' => trim($_GET['category_name'] ?? ''),
    'start_date' => $_GET['start_date'] ?? null,
    'end_date' => $_GET['end_date'] ?? null,
];

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

foreach ($entries as $e):
?>
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
    <td><?= $e['comment'] !== null && $e['comment'] !== '' ? nl2br(htmlspecialchars($e['comment'])) : '<span class="text-muted">No comment</span>' ?></td>
    <td>
        <a href="<?= h(route_url('entry_edit', ['id' => (int)$e['id'], 'return' => $returnTo])) ?>" class="btn btn-sm btn-outline-primary mb-1">Edit</a>
        <form method="post" class="d-inline" action="<?= h(route_url('entries')) ?><?= $returnQuery !== '' ? '?' . htmlspecialchars($returnQuery) : '' ?>">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
            <?php /*<button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this entry?');">Delete</button> */ ?>
        </form>
    </td>
</tr>
<?php endforeach; ?>
