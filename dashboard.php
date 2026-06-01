<?php
require __DIR__ . '/middleware.php';
require_login();

$user_id = user_id();
$tz = user_timezone_object();
$now = new DateTime('now', $tz);
[$ws, $we] = week_bounds($now);

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

$week_start_utc = (clone $ws)->setTimezone(new DateTimeZone('UTC'));
$week_end_utc = (clone $we)->setTimezone(new DateTimeZone('UTC'));

$totalsStmt = $pdo->prepare('
    SELECT cl.name AS client_name, p.name AS project_name, wc.name AS category_name, SUM(tr.duration) AS secs
    FROM time_records tr
    LEFT JOIN projects p ON p.id = tr.project_id
    LEFT JOIN clients cl ON cl.id = p.client_id
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.user_id = :user_id
      AND tr.duration IS NOT NULL
      AND tr.start_time >= :week_start
      AND tr.start_time <= :week_end
    GROUP BY cl.name, p.name, wc.name
    ORDER BY cl.name, p.name, wc.name
');
$totalsStmt->execute([
    ':user_id' => $user_id,
    ':week_start' => $week_start_utc->format('Y-m-d H:i:s'),
    ':week_end' => $week_end_utc->format('Y-m-d H:i:s'),
]);
$totals = $totalsStmt->fetchAll(PDO::FETCH_ASSOC);
$weekly_total_secs = 0;
foreach ($totals as $totalRow) {
    $weekly_total_secs += (int)($totalRow['secs'] ?? 0);
}

$entriesStmt = $pdo->prepare('
    SELECT tr.*, p.name AS project_name, cl.name AS client_name, wc.name AS category_name
    FROM time_records tr
    LEFT JOIN projects p ON p.id = tr.project_id
    LEFT JOIN clients cl ON cl.id = p.client_id
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.user_id = ?
    ORDER BY tr.start_time DESC
    LIMIT 5
');
$entriesStmt->execute([$user_id]);
$entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);

$runningStmt = $pdo->prepare('
    SELECT tr.id, tr.start_time, p.name AS project_name, cl.name AS client_name, wc.name AS category_name
    FROM time_records tr
    LEFT JOIN projects p ON p.id = tr.project_id
    LEFT JOIN clients cl ON cl.id = p.client_id
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.user_id = ? AND tr.duration IS NULL
');
$runningStmt->execute([$user_id]);
$running = $runningStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryChartStmt = $pdo->prepare('
    SELECT COALESCE(wc.name, "Uncategorized") AS category_name, SUM(tr.duration) AS secs
    FROM time_records tr
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.user_id = :user_id
      AND tr.duration IS NOT NULL
      AND tr.start_time >= :week_start
      AND tr.start_time <= :week_end
    GROUP BY category_name
    ORDER BY secs DESC
');
$categoryChartStmt->execute([
    ':user_id' => $user_id,
    ':week_start' => $week_start_utc->format('Y-m-d H:i:s'),
    ':week_end' => $week_end_utc->format('Y-m-d H:i:s'),
]);
$categoryChartRows = $categoryChartStmt->fetchAll(PDO::FETCH_ASSOC);

$dailyTrendStmt = $pdo->prepare('
    SELECT tr.start_time, tr.duration
    FROM time_records tr
    WHERE tr.user_id = :user_id
      AND tr.duration IS NOT NULL
      AND tr.start_time >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 DAY)
    ORDER BY tr.start_time ASC
');
$dailyTrendStmt->execute([':user_id' => $user_id]);
$dailyTrendRows = $dailyTrendStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [];
$categoryHours = [];
foreach ($categoryChartRows as $row) {
    $categoryLabels[] = (string)$row['category_name'];
    $categoryHours[] = round(((int)$row['secs']) / 3600, 2);
}

$dailyByDay = [];
foreach ($dailyTrendRows as $row) {
    $dt = new DateTime((string)$row['start_time'], new DateTimeZone('UTC'));
    $dt->setTimezone($tz);
    $localDay = $dt->format('Y-m-d');
    $dailyByDay[$localDay] = ($dailyByDay[$localDay] ?? 0) + ((int)$row['duration'] / 3600);
}

$trendLabels = [];
$trendHours = [];
$trendStart = (new DateTime('today', $tz))->modify('-13 days');
for ($i = 0; $i < 14; $i++) {
    $day = (clone $trendStart)->modify("+{$i} days");
    $key = $day->format('Y-m-d');
    $trendLabels[] = $day->format('M j');
    $trendHours[] = round(($dailyByDay[$key] ?? 0), 2);
}

$page_title = 'Dashboard';
include __DIR__ . '/header.php';
?>
<h1 class="mb-4">Dashboard</h1>
<p>Week of <?= htmlspecialchars($ws->format('M d, Y')) ?> – <?= htmlspecialchars($we->format('M d, Y')) ?></p>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Weekly Totals</h2>
    <?php if (!empty($totals)): ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Category</th>
                    <th>Total Time</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($totals as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars(($t['client_name'] ? $t['client_name'] . ' — ' : '') . ($t['project_name'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($t['category_name'] ?? '—') ?></td>
                        <td><?= fmt_dur($t['secs']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

            <tfoot class="table-light">
                <tr>
                    <td colspan="2" class="text-end fw-semibold">Overall Total</td>
                    <td class="fw-semibold"><?= fmt_dur($weekly_total_secs) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <p class="text-muted">No time logged this week.</p>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card p-3 h-100 dashboard-chart-card">
            <h2 class="h5 mb-3">This Week by Category</h2>
            <?php if (!empty($categoryLabels)): ?>
                <div class="dashboard-donut-wrap">
                    <canvas id="categoryBreakdownChart"></canvas>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No completed entries yet this week.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100 dashboard-chart-card dashboard-trend-card">
            <h2 class="h5 mb-3">Last 14 Days Trend</h2>
            <div class="dashboard-trend-canvas-wrap d-flex align-items-center">
                <canvas id="dailyTrendChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Recent Entries</h2>
    <?php if (!empty($entries)): ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Category</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Duration</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars(($e['client_name'] ? $e['client_name'] . ' — ' : '') . ($e['project_name'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($e['category_name'] ?? '—') ?></td>
                        <td><?= formatLocalTimeRecentEntries($e['start_time']) ?></td>
                        <td><?= $e['end_time'] ? formatLocalTimeRecentEntries($e['end_time']) : '—' ?></td>
                        <td><?= $e['duration'] !== null ? fmt_dur($e['duration']) : 'Running…' ?></td>
                        <td><?= $e['comment'] !== null ? htmlspecialchars($e['comment']) : 'No comment' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="d-grid">
            <a href="entries.php" class="btn btn-outline-secondary btn-sm">All Entries</a>
        </div>
    <?php else: ?>
        <p class="text-muted">No entries yet.</p>
    <?php endif; ?>
</div>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Start a Timer</h2>
    <form method="post" action="track.php" class="row g-2 align-items-end">
        <div class="col-md-6">
            <label for="dashboard_project_id" class="form-label">Project</label>
            <select id="dashboard_project_id" name="project_id" class="form-select">
                <?php foreach ($projects as $project): ?>
                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['client_name'] . ' — ' . $project['project_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="dashboard_category_id" class="form-label">Category</label>
            <select id="dashboard_category_id" name="category_id" class="form-select">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" name="start_timer" value="1" class="btn btn-primary w-100">Start</button>
        </div>
    </form>
</div>

<div class="card mb-4 p-3">
    <h2 class="h5 mb-3">Running Timers</h2>
    <?php if (!empty($running)): ?>
        <ul class="list-group mb-4">
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
                    <form method="post" action="track.php" class="m-0">
                        <input type="hidden" name="return_to" value="dashboard.php">
                        <button type="submit" name="stop_timer" value="<?= $r['id'] ?>" class="btn btn-danger btn-sm">Stop</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><em>No timers running.</em></p>
    <?php endif; ?>
</div>

<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
(() => {
    if (typeof Chart === 'undefined') return;

    const categoryLabels = <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const categoryHours = <?= json_encode($categoryHours, JSON_NUMERIC_CHECK) ?>;
    const trendLabels = <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const trendHours = <?= json_encode($trendHours, JSON_NUMERIC_CHECK) ?>;

    const palette = ['#00B6DC', '#17D7D7', '#E0F7F7', '#F7B55C', '#F79435', '#EA6400'];

    const categoryCanvas = document.getElementById('categoryBreakdownChart');
    if (categoryCanvas && categoryLabels.length > 0) {
        new Chart(categoryCanvas, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryHours,
                    backgroundColor: categoryLabels.map((_, i) => palette[i % palette.length]),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.label}: ${ctx.parsed}h`
                        }
                    }
                }
            }
        });
    }

    const donutWrap = document.querySelector('.dashboard-donut-wrap');
    const trendWrap = document.querySelector('.dashboard-trend-canvas-wrap');
    function syncTrendHeightToDonut() {
        if (!donutWrap || !trendWrap) return;
        const donutHeight = donutWrap.getBoundingClientRect().height;
        if (donutHeight > 0) {
            trendWrap.style.height = `${Math.round(donutHeight)}px`;
        }
    }

    syncTrendHeightToDonut();
    window.addEventListener('resize', syncTrendHeightToDonut);
    if (typeof ResizeObserver !== 'undefined' && donutWrap) {
        const ro = new ResizeObserver(syncTrendHeightToDonut);
        ro.observe(donutWrap);
    }

    const trendCanvas = document.getElementById('dailyTrendChart');
    if (trendCanvas) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Hours Tracked',
                    data: trendHours,
                    borderColor: palette[0],
                    backgroundColor: 'rgba(0, 182, 220, 0.18)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: palette[4],
                    pointBorderColor: palette[0]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Hours' }
                    }
                }
            }
        });
    }
})();
</script>
<?php include __DIR__ . '/footer.php'; ?>
