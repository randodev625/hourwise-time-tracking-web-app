<?php
require __DIR__ . '/../../core/middleware.php';
require_login();

$user_id = user_id();
$tz = user_timezone_object();
$now = new DateTime('now', $tz);
$today = new DateTime('today', $tz);
[$defaultStart, $defaultEnd] = week_bounds($now);

$requestedStartDate = trim((string)($_GET['start_date'] ?? ''));
$requestedEndDate = trim((string)($_GET['end_date'] ?? ''));
$rangeValidationMessage = null;

$parseDashboardDate = static function (string $value, DateTimeZone $timezone): ?DateTime {
    $dt = DateTime::createFromFormat('Y-m-d', $value, $timezone);
    $errors = DateTime::getLastErrors();
    if (!$dt || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        return null;
    }

    return $dt->setTime(0, 0, 0);
};

$rangeStartLocal = (clone $defaultStart)->setTime(0, 0, 0);
$rangeEndLocal = (clone $defaultEnd)->setTime(23, 59, 59);

if ($requestedStartDate !== '' || $requestedEndDate !== '') {
    $parsedStart = $requestedStartDate !== '' ? $parseDashboardDate($requestedStartDate, $tz) : null;
    $parsedEnd = $requestedEndDate !== '' ? $parseDashboardDate($requestedEndDate, $tz) : null;

    if ($parsedStart === null || $parsedEnd === null) {
        $rangeValidationMessage = 'Choose valid start and end dates to filter the dashboard.';
    } elseif ($parsedStart > $parsedEnd) {
        $rangeValidationMessage = 'Start date must be on or before the end date.';
    } else {
        $rangeStartLocal = $parsedStart;
        $rangeEndLocal = (clone $parsedEnd)->setTime(23, 59, 59);
    }
}

$rangeStartDateValue = $requestedStartDate !== '' ? $requestedStartDate : $rangeStartLocal->format('Y-m-d');
$rangeEndDateValue = $requestedEndDate !== '' ? $requestedEndDate : $rangeEndLocal->format('Y-m-d');
$rangeLabel = $rangeStartLocal->format('M d, Y') . ' – ' . $rangeEndLocal->format('M d, Y');
$rangeTotalLabel = $rangeStartLocal->format('Y-m-d') === $rangeEndLocal->format('Y-m-d')
    ? $rangeStartLocal->format('M d, Y')
    : $rangeLabel;

$range_start_utc = (clone $rangeStartLocal)->setTimezone(new DateTimeZone('UTC'));
$range_end_utc = (clone $rangeEndLocal)->setTimezone(new DateTimeZone('UTC'));

$totalsStmt = $pdo->prepare('
    SELECT cl.name AS client_name, p.name AS project_name, wc.name AS category_name, SUM(tr.duration) AS secs
    FROM time_records tr
    LEFT JOIN projects p ON p.id = tr.project_id
    LEFT JOIN clients cl ON cl.id = p.client_id
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.user_id = :user_id
      AND tr.duration IS NOT NULL
      AND tr.start_time >= :range_start
      AND tr.start_time <= :range_end
    GROUP BY cl.name, p.name, wc.name
    ORDER BY cl.name, p.name, wc.name
');
$totalsStmt->execute([
    ':user_id' => $user_id,
    ':range_start' => $range_start_utc->format('Y-m-d H:i:s'),
    ':range_end' => $range_end_utc->format('Y-m-d H:i:s'),
]);
$totals = $totalsStmt->fetchAll(PDO::FETCH_ASSOC);
$range_total_secs = 0;
$uniqueProjects = [];
foreach ($totals as $totalRow) {
    $range_total_secs += (int)($totalRow['secs'] ?? 0);
    $projectKey = trim((string)($totalRow['project_name'] ?? ''));
    if ($projectKey !== '') {
        $uniqueProjects[$projectKey] = true;
    }
}

$categoryChartStmt = $pdo->prepare('
    SELECT COALESCE(wc.name, "Uncategorized") AS category_name, SUM(tr.duration) AS secs
    FROM time_records tr
    LEFT JOIN work_categories wc ON wc.id = tr.category_id
    WHERE tr.user_id = :user_id
      AND tr.duration IS NOT NULL
      AND tr.start_time >= :range_start
      AND tr.start_time <= :range_end
    GROUP BY category_name
    ORDER BY secs DESC
');
$categoryChartStmt->execute([
    ':user_id' => $user_id,
    ':range_start' => $range_start_utc->format('Y-m-d H:i:s'),
    ':range_end' => $range_end_utc->format('Y-m-d H:i:s'),
]);
$categoryChartRows = $categoryChartStmt->fetchAll(PDO::FETCH_ASSOC);

$projectChartStmt = $pdo->prepare('
    SELECT COALESCE(cl.name, "No Client") AS client_name,
           COALESCE(p.name, "Unassigned Project") AS project_name,
           SUM(tr.duration) AS secs
    FROM time_records tr
    LEFT JOIN projects p ON p.id = tr.project_id
    LEFT JOIN clients cl ON cl.id = p.client_id
    WHERE tr.user_id = :user_id
      AND tr.duration IS NOT NULL
      AND tr.start_time >= :range_start
      AND tr.start_time <= :range_end
    GROUP BY p.id, client_name, project_name
    ORDER BY secs DESC, client_name, project_name
    LIMIT 6
');
$projectChartStmt->execute([
    ':user_id' => $user_id,
    ':range_start' => $range_start_utc->format('Y-m-d H:i:s'),
    ':range_end' => $range_end_utc->format('Y-m-d H:i:s'),
]);
$projectChartRows = $projectChartStmt->fetchAll(PDO::FETCH_ASSOC);

$dailyTrendStmt = $pdo->prepare('
    SELECT tr.start_time, tr.duration
    FROM time_records tr
    WHERE tr.user_id = :user_id
      AND tr.duration IS NOT NULL
      AND tr.start_time >= :range_start
      AND tr.start_time <= :trend_end
    ORDER BY tr.start_time ASC
');
$dailyTrendStmt->execute([
    ':user_id' => $user_id,
    ':range_start' => $range_start_utc->format('Y-m-d H:i:s'),
    ':trend_end' => $range_end_utc->format('Y-m-d H:i:s'),
]);
$dailyTrendRows = $dailyTrendStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [];
$categoryHours = [];
foreach ($categoryChartRows as $row) {
    $categoryLabels[] = (string)$row['category_name'];
    $categoryHours[] = round(((int)$row['secs']) / 3600, 2);
}

$projectLabels = [];
$projectHours = [];
foreach ($projectChartRows as $row) {
    $projectLabels[] = (string)$row['project_name'];
    $projectHours[] = round(((int)$row['secs']) / 3600, 2);
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
$trendCursor = (clone $rangeStartLocal)->setTime(0, 0, 0);
while ($trendCursor <= $rangeEndLocal) {
    $dayKey = $trendCursor->format('Y-m-d');
    $trendLabels[] = $trendCursor->format('M j');
    $trendHours[] = round(($dailyByDay[$dayKey] ?? 0), 2);
    $trendCursor->modify('+1 day');
}

$projectsWorkedCount = count($uniqueProjects);
$activeDaysCount = count(array_filter($dailyByDay, static fn ($hours) => (float)$hours > 0));

render_layout_header();
?>
<div class="mb-4">
    <h1 class="mb-1">Dashboard</h1>
    <button type="button"
        class="dashboard-filter-trigger"
        data-bs-toggle="modal"
        data-bs-target="#dashboardFilterModal"
        aria-label="Open dashboard date filter">
        <span>Showing <?= htmlspecialchars($rangeLabel) ?></span>
        <i class="fa-solid fa-caret-down" aria-hidden="true"></i>
    </button>
    <?php if ($rangeValidationMessage !== null): ?>
        <p class="text-danger small mb-0 mt-2"><?= htmlspecialchars($rangeValidationMessage) ?></p>
    <?php endif; ?>
</div>

<div class="modal fade" id="dashboardFilterModal" tabindex="-1" aria-labelledby="dashboardFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dashboard-filter-modal">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5 mb-0" id="dashboardFilterModalLabel">Filter Dashboard</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <form method="get" action="<?= h(route_url('dashboard')) ?>" class="dashboard-filter-form" id="dashboardFilterForm">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-6">
                            <label for="dashboard_start_date" class="form-label mb-1">Start</label>
                            <input type="date" id="dashboard_start_date" name="start_date" class="form-control"
                                value="<?= htmlspecialchars($rangeStartDateValue) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="dashboard_end_date" class="form-label mb-1">End</label>
                            <input type="date" id="dashboard_end_date" name="end_date" class="form-control"
                                value="<?= htmlspecialchars($rangeEndDateValue) ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-1 flex-wrap mt-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dashboard-range="week">This Week</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dashboard-range="lastweek">Last Week</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dashboard-range="month">This Month</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dashboard-range="30days">Last 30 Days</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dashboard-range="ytd">YTD</button>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= h(route_url('dashboard')) ?>" class="btn btn-outline-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 h-100 dashboard-kpi-card">
            <p class="dashboard-kpi-label mb-2">Total Hours</p>
            <div class="dashboard-kpi-value"><?= htmlspecialchars(fmt_dur($range_total_secs)) ?></div>
            <p class="text-muted small mb-0"><?= htmlspecialchars($rangeTotalLabel) ?></p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 h-100 dashboard-kpi-card">
            <p class="dashboard-kpi-label mb-2">Projects Worked On</p>
            <div class="dashboard-kpi-value"><?= (int)$projectsWorkedCount ?></div>
            <p class="text-muted small mb-0">Unique projects with tracked time in this range</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 h-100 dashboard-kpi-card">
            <p class="dashboard-kpi-label mb-2">Active Days</p>
            <div class="dashboard-kpi-value"><?= (int)$activeDaysCount ?></div>
            <p class="text-muted small mb-0">Days with at least one tracked entry</p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card p-3 h-100 dashboard-chart-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <h2 class="h5 mb-0">Category Breakdown</h2>
                <p class="text-muted small mb-0"><?= htmlspecialchars($rangeTotalLabel) ?></p>
            </div>
            <?php if (!empty($categoryLabels)): ?>
                <div class="dashboard-donut-wrap">
                    <canvas id="categoryBreakdownChart"></canvas>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No completed entries in this range.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100 dashboard-chart-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <h2 class="h5 mb-0">Top Projects</h2>
                <p class="text-muted small mb-0"><?= htmlspecialchars($rangeTotalLabel) ?></p>
            </div>
            <?php if (!empty($projectLabels)): ?>
                <div class="dashboard-bar-canvas-wrap">
                    <canvas id="projectBreakdownChart"></canvas>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No completed entries in this range.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card p-3 h-100 dashboard-chart-card dashboard-trend-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Activity Trend</h2>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($rangeTotalLabel) ?></p>
                </div>
            </div>
            <div class="dashboard-trend-canvas-wrap d-flex align-items-center">
                <canvas id="dailyTrendChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 p-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <h2 class="h5 mb-0">Time Totals</h2>
        <p class="text-muted small mb-0"><?= htmlspecialchars($rangeTotalLabel) ?></p>
    </div>
    <?php if (!empty($totals)): ?>
        <div class="dashboard-totals-table-wrap">
            <table class="table table-striped table-bordered mb-0">
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
                        <td colspan="2" class="text-end fw-semibold">Range Total</td>
                        <td class="fw-semibold"><?= fmt_dur($range_total_secs) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No time logged for this range.</p>
    <?php endif; ?>
</div>

<div class="card mb-4 p-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Quick Links</h2>
            <p class="text-muted small mb-0">Jump straight into the areas you use most.</p>
        </div>
        <div>
            <a href="<?= h(route_url('track')) ?>" class="btn btn-primary btn-sm">Track Time</a>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-sm-6 col-lg-4">
            <a href="<?= h(route_url('track')) ?>" class="dashboard-quick-link card h-100 text-decoration-none">
                <div class="card-body">
                    <div class="dashboard-quick-link-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                    <h3 class="h6 mb-1">Track Time</h3>
                    <p class="mb-0 text-muted small">Start or stop a timer and log time quickly.</p>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4">
            <a href="<?= h(route_url('entries')) ?>" class="dashboard-quick-link card h-100 text-decoration-none">
                <div class="card-body">
                    <div class="dashboard-quick-link-icon"><i class="fa-solid fa-table-list" aria-hidden="true"></i></div>
                    <h3 class="h6 mb-1">View Entries</h3>
                    <p class="mb-0 text-muted small">Review, filter, edit, and export tracked time.</p>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4">
            <a href="<?= h(route_url('projects')) ?>" class="dashboard-quick-link card h-100 text-decoration-none">
                <div class="card-body">
                    <div class="dashboard-quick-link-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></div>
                    <h3 class="h6 mb-1">Projects</h3>
                    <p class="mb-0 text-muted small">Manage active projects and keep work organized.</p>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4">
            <a href="<?= h(route_url('clients')) ?>" class="dashboard-quick-link card h-100 text-decoration-none">
                <div class="card-body">
                    <div class="dashboard-quick-link-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></div>
                    <h3 class="h6 mb-1">Clients</h3>
                    <p class="mb-0 text-muted small">Add clients and maintain your account list.</p>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4">
            <a href="<?= h(route_url('categories')) ?>" class="dashboard-quick-link card h-100 text-decoration-none">
                <div class="card-body">
                    <div class="dashboard-quick-link-icon"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
                    <h3 class="h6 mb-1">Project Categories</h3>
                    <p class="mb-0 text-muted small">Create and manage project categories for cleaner reporting.</p>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4">
            <a href="<?= h(route_url('account')) ?>" class="dashboard-quick-link card h-100 text-decoration-none">
                <div class="card-body">
                    <div class="dashboard-quick-link-icon"><i class="fa-solid fa-user-gear" aria-hidden="true"></i></div>
                    <h3 class="h6 mb-1">Account</h3>
                    <p class="mb-0 text-muted small">Update your profile, timezone, password, and security settings.</p>
                </div>
            </a>
        </div>
        <?php if (is_admin_user()): ?>
            <div class="col-sm-6 col-lg-4">
                <a href="<?= h(route_url('admin_settings')) ?>" class="dashboard-quick-link card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="dashboard-quick-link-icon"><i class="fa-solid fa-sliders" aria-hidden="true"></i></div>
                        <h3 class="h6 mb-1">Admin Settings</h3>
                        <p class="mb-0 text-muted small">Manage registration, mail, and app diagnostics.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
    (() => {
        if (typeof Chart === 'undefined') return;

        const categoryLabels = <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const categoryHours = <?= json_encode($categoryHours, JSON_NUMERIC_CHECK) ?>;
        const projectLabels = <?= json_encode($projectLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const projectHours = <?= json_encode($projectHours, JSON_NUMERIC_CHECK) ?>;
        const trendLabels = <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const trendHours = <?= json_encode($trendHours, JSON_NUMERIC_CHECK) ?>;
        const dashboardStartInput = document.getElementById('dashboard_start_date');
        const dashboardEndInput = document.getElementById('dashboard_end_date');
        const dashboardRangeButtons = document.querySelectorAll('[data-dashboard-range]');
        const dashboardFilterModal = document.getElementById('dashboardFilterModal');
        const shouldOpenFilterModal = <?= $rangeValidationMessage !== null ? 'true' : 'false' ?>;
        const rootStyles = getComputedStyle(document.documentElement);
        const cssVar = (name, fallback) => {
            const value = rootStyles.getPropertyValue(name).trim();
            return value || fallback;
        };
        const palette = [
            cssVar('--app-chart-brand-700', '#1f8fdd'),
            cssVar('--app-chart-brand-600', '#2da4f0'),
            cssVar('--app-chart-brand-500', '#42bef5'),
            cssVar('--app-chart-brand-200', '#cfeffc'),
            cssVar('--app-chart-ink-500', '#8a96a1'),
            cssVar('--app-chart-danger-700', '#e6606b')
        ];
        const trendFillColor = cssVar('--app-chart-line-fill', 'rgba(66, 190, 245, 0.18)');

        function formatHours(value) {
            return Number(value)
                .toFixed(2)
                .replace(/\.00$/, '')
                .replace(/(\.\d)0$/, '$1');
        }

        function wrapAxisLabel(label, maxCharsPerLine = 18) {
            const normalized = String(label || '').replace(/\s+—\s+/g, ' — ');
            const words = normalized.split(' ');
            const lines = [];
            let currentLine = '';

            words.forEach((word) => {
                if (word.length > maxCharsPerLine) {
                    if (currentLine) {
                        lines.push(currentLine);
                        currentLine = '';
                    }

                    for (let i = 0; i < word.length; i += maxCharsPerLine) {
                        lines.push(word.slice(i, i + maxCharsPerLine));
                    }
                    return;
                }

                const nextLine = currentLine ? `${currentLine} ${word}` : word;
                if (nextLine.length > maxCharsPerLine) {
                    if (currentLine) {
                        lines.push(currentLine);
                    }
                    currentLine = word;
                } else {
                    currentLine = nextLine;
                }
            });

            if (currentLine) {
                lines.push(currentLine);
            }

            return lines.length > 0 ? lines : [''];
        }

        if (dashboardStartInput && dashboardEndInput) {
            const presetRanges = {
                week: {
                    start: '<?= h($defaultStart->format('Y-m-d')) ?>',
                    end: '<?= h($defaultEnd->format('Y-m-d')) ?>',
                },
                lastweek: {
                    start: '<?= h((clone $defaultStart)->modify('-7 days')->format('Y-m-d')) ?>',
                    end: '<?= h((clone $defaultEnd)->modify('-7 days')->format('Y-m-d')) ?>',
                },
                month: {
                    start: '<?= h((clone $today)->modify('first day of this month')->format('Y-m-d')) ?>',
                    end: '<?= h($today->format('Y-m-d')) ?>',
                },
                '30days': {
                    start: '<?= h((clone $today)->modify('-29 days')->format('Y-m-d')) ?>',
                    end: '<?= h($today->format('Y-m-d')) ?>',
                },
                ytd: {
                    start: '<?= h((clone $today)->setDate((int)$today->format('Y'), 1, 1)->format('Y-m-d')) ?>',
                    end: '<?= h($today->format('Y-m-d')) ?>',
                }
            };

            dashboardRangeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const preset = presetRanges[button.dataset.dashboardRange || ''];
                    if (!preset) return;

                    dashboardStartInput.value = preset.start;
                    dashboardEndInput.value = preset.end;
                    if (typeof button.form?.requestSubmit === 'function') {
                        button.form.requestSubmit();
                    } else {
                        button.form?.submit();
                    }
                });
            });
        }

        if (shouldOpenFilterModal && dashboardFilterModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const filterModal = new bootstrap.Modal(dashboardFilterModal);
            filterModal.show();
        }

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
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.label}: ${ctx.parsed}h`
                            }
                        }
                    }
                }
            });
        }

        const projectCanvas = document.getElementById('projectBreakdownChart');
        if (projectCanvas && projectLabels.length > 0) {
            new Chart(projectCanvas, {
                type: 'bar',
                data: {
                    labels: projectLabels,
                    datasets: [{
                        label: 'Hours Tracked',
                        data: projectHours,
                        backgroundColor: palette[0],
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: (items) => projectLabels[items[0]?.dataIndex ?? 0] || '',
                                label: (ctx) => `Hours Tracked: ${formatHours(ctx.parsed.x)}h`
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            },
                            ticks: {
                                callback: (value) => formatHours(value)
                            }
                        },
                        y: {
                            afterFit(scale) {
                                scale.width = 140;
                            },
                            ticks: {
                                autoSkip: false,
                                callback: (_, index) => wrapAxisLabel(projectLabels[index] || '')
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
            const trendMaxTicks = () => {
                if (trendHours.length > 180) return 12;
                if (trendHours.length > 90) return 10;
                if (trendHours.length > 45) return 9;
                return 8;
            };
            const pointRadius = trendHours.length > 45 ? 0 : 3;
            const movingAverage = trendHours.map((_, index, values) => {
                const start = Math.max(0, index - 6);
                const window = values.slice(start, index + 1);
                const total = window.reduce((sum, value) => sum + Number(value || 0), 0);
                return Number((total / window.length).toFixed(2));
            });

            const trendChart = new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Hours Tracked',
                        data: trendHours,
                        borderColor: palette[0],
                        backgroundColor: trendFillColor,
                        fill: true,
                        tension: 0.3,
                        pointRadius,
                        pointHoverRadius: pointRadius === 0 ? 4 : 5,
                        pointBackgroundColor: palette[4],
                        pointBorderColor: palette[0]
                    }, {
                        label: '7-Day Average',
                        data: movingAverage,
                        borderColor: palette[5],
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        borderDash: [4, 4]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.dataset.label}: ${formatHours(ctx.parsed.y)}h`
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                autoSkip: true,
                                maxTicksLimit: trendMaxTicks()
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        }
                    }
                }
            });
        }
    })();
</script>
<?php render_layout_footer(); ?>
