<?php
function start_session(array $config): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name($config['app']['session_name'] ?? 'tt_sess');
    session_set_cookie_params([
        'lifetime' => $config['app']['session_lifetime'] ?? 0,
        'path' => '/',
        'secure' => $config['app']['session_secure'] ?? false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['__regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['__regenerated'] = time();
    }
}

function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

function user_id(): ?int { return $_SESSION['user']['id'] ?? null; }

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function set_user_session(array $user): void {
    $timezone = (string)($user['timezone'] ?? app_default_timezone());
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $timezone = app_default_timezone();
    }

    $_SESSION['user'] = [
        'id' => (int)($user['id'] ?? 0),
        'email' => (string)($user['email'] ?? ''),
        'display_name' => (string)($user['display_name'] ?? ''),
        'avatar_path' => (string)($user['avatar_path'] ?? ''),
        'timezone' => $timezone,
    ];
}

function avatar_url(?string $path): ?string {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (!preg_match('#^/uploads/avatars/avatar_[0-9]+_[a-f0-9]{16}\.(jpg|png|webp)$#', $path)) {
        return null;
    }
    return $path;
}

function avatar_file_path(?string $path): ?string {
    $path = avatar_url($path);
    if ($path === null) {
        return null;
    }

    $dir = realpath(__DIR__ . '/uploads/avatars');
    if ($dir === false) {
        return null;
    }

    $fullPath = $dir . '/' . basename($path);
    $realPath = realpath($fullPath);
    if ($realPath === false || !str_starts_with($realPath, $dir . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $realPath;
}

function user_display_name(?array $user): string {
    $display = trim((string)($user['display_name'] ?? ''));
    if ($display !== '') return $display;
    $email = trim((string)($user['email'] ?? ''));
    if ($email !== '') return preg_replace('/@.*$/', '', $email);
    return 'Account';
}

function user_initials(?array $user): string {
    $name = trim((string)($user['display_name'] ?? ''));
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if ($initials !== '') return $initials;
    }
    $email = trim((string)($user['email'] ?? ''));
    if ($email !== '') return strtoupper(substr($email, 0, 1));
    return 'U';
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

function rotate_csrf_token(): string {
    unset($_SESSION['csrf']);
    return csrf_token();
}

function refresh_session_security(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    session_regenerate_id(true);
    $_SESSION['__regenerated'] = time();
    rotate_csrf_token();
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function safe_redirect_path(string $value, string $fallback = 'entries.php', array $allowedPaths = []): string {
    $value = trim($value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return $fallback;
    }

    if (str_contains($value, '\\') || str_starts_with($value, '//')) {
        return $fallback;
    }

    $parts = parse_url($value);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    $path = (string)($parts['path'] ?? '');
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, '..')) {
        return $fallback;
    }

    $allowedPaths = $allowedPaths ?: ['entries.php', 'track.php', 'dashboard.php'];
    if (!in_array($path, $allowedPaths, true)) {
        return $fallback;
    }

    $safePath = $path;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $safePath .= '?' . $parts['query'];
    }

    return $safePath;
}

function add_query_arg(string $path, string $key, string $value): string {
    $separator = str_contains($path, '?') ? '&' : '?';
    return $path . $separator . rawurlencode($key) . '=' . rawurlencode($value);
}

function client_ip_address(): string {
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : 'unknown';
}

function auth_rate_limit_policy(string $action): array {
    return match ($action) {
        'login' => [
            'max_attempts' => 10,
            'window_minutes' => 15,
            'lock_minutes' => 15,
        ],
        'password_reset' => [
            'max_attempts' => 5,
            'window_minutes' => 60,
            'lock_minutes' => 60,
        ],
        default => [
            'max_attempts' => 10,
            'window_minutes' => 15,
            'lock_minutes' => 15,
        ],
    };
}

function auth_rate_limit_table_ready(PDO $pdo): bool {
    static $ready = null;
    if ($ready === null) {
        $ready = table_exists($pdo, 'auth_rate_limits');
    }
    return $ready;
}

function auth_rate_limit_keys(string $subject): array {
    $subject = strtolower(trim($subject));
    return [
        hash('sha256', $subject),
        hash('sha256', client_ip_address()),
    ];
}

function auth_rate_limit_status(PDO $pdo, string $action, string $subject): array {
    if (!auth_rate_limit_table_ready($pdo)) {
        return ['limited' => false, 'available' => false];
    }

    [$subjectHash, $ipHash] = auth_rate_limit_keys($subject);
    $stmt = $pdo->prepare(
        'SELECT attempts, first_attempt_at, locked_until
         FROM auth_rate_limits
         WHERE action = ? AND subject_hash = ? AND ip_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$action, $subjectHash, $ipHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['limited' => false, 'available' => true];
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if (!empty($row['locked_until'])) {
        $lockedUntil = new DateTimeImmutable($row['locked_until'], new DateTimeZone('UTC'));
        if ($lockedUntil > $now) {
            return [
                'limited' => true,
                'available' => true,
                'locked_until' => $lockedUntil,
            ];
        }
    }

    $policy = auth_rate_limit_policy($action);
    $firstAttemptAt = new DateTimeImmutable($row['first_attempt_at'], new DateTimeZone('UTC'));
    if ($firstAttemptAt->modify('+' . (int)$policy['window_minutes'] . ' minutes') <= $now) {
        return ['limited' => false, 'available' => true];
    }

    return ['limited' => false, 'available' => true];
}

function auth_rate_limit_record_attempt(PDO $pdo, string $action, string $subject): void {
    if (!auth_rate_limit_table_ready($pdo)) {
        return;
    }

    [$subjectHash, $ipHash] = auth_rate_limit_keys($subject);
    $policy = auth_rate_limit_policy($action);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $nowSql = $now->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, attempts, first_attempt_at
             FROM auth_rate_limits
             WHERE action = ? AND subject_hash = ? AND ip_hash = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$action, $subjectHash, $ipHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $insert = $pdo->prepare(
                'INSERT INTO auth_rate_limits
                    (action, subject_hash, ip_hash, attempts, first_attempt_at, last_attempt_at, locked_until)
                 VALUES (?, ?, ?, 1, ?, ?, NULL)'
            );
            $insert->execute([$action, $subjectHash, $ipHash, $nowSql, $nowSql]);
            $pdo->commit();
            return;
        }

        $firstAttemptAt = new DateTimeImmutable($row['first_attempt_at'], new DateTimeZone('UTC'));
        $windowExpired = $firstAttemptAt->modify('+' . (int)$policy['window_minutes'] . ' minutes') <= $now;
        $attempts = $windowExpired ? 1 : ((int)$row['attempts'] + 1);
        $firstAttemptSql = $windowExpired ? $nowSql : $row['first_attempt_at'];
        $lockedUntilSql = null;

        if ($attempts >= (int)$policy['max_attempts']) {
            $lockedUntilSql = $now->modify('+' . (int)$policy['lock_minutes'] . ' minutes')->format('Y-m-d H:i:s');
        }

        $update = $pdo->prepare(
            'UPDATE auth_rate_limits
             SET attempts = ?, first_attempt_at = ?, last_attempt_at = ?, locked_until = ?
             WHERE id = ?'
        );
        $update->execute([$attempts, $firstAttemptSql, $nowSql, $lockedUntilSql, (int)$row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function auth_rate_limit_clear(PDO $pdo, string $action, string $subject): void {
    if (!auth_rate_limit_table_ready($pdo)) {
        return;
    }

    [$subjectHash, $ipHash] = auth_rate_limit_keys($subject);
    $stmt = $pdo->prepare('DELETE FROM auth_rate_limits WHERE action = ? AND subject_hash = ? AND ip_hash = ?');
    $stmt->execute([$action, $subjectHash, $ipHash]);
}

function iso_datetime(string $dt): string { return date('Y-m-d H:i:s', strtotime($dt)); }

function week_bounds(DateTime $ref): array {
    $start = clone $ref;

    // Sunday-start week
    if ($start->format('w') !== '0') {
        $start->modify('last sunday');
    }

    $start->setTime(0, 0, 0);

    $end = clone $start;
    $end->modify('+6 days')->setTime(23, 59, 59);

    return [$start, $end];
}

function token64(): string { return bin2hex(random_bytes(32)); }

function base_url(string $path = ''): string {
    $cfg = require __DIR__ . '/config.php';
    return rtrim($cfg['app']['base_url'], '/') . '/' . ltrim($path, '/');
}

function app_default_timezone(): string {
    $cfg = require __DIR__ . '/config.php';
    $tz = (string)($cfg['app']['timezone'] ?? 'UTC');
    return in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'UTC';
}

function user_timezone(): string {
    $tz = (string)($_SESSION['user']['timezone'] ?? '');
    if ($tz !== '' && in_array($tz, DateTimeZone::listIdentifiers(), true)) {
        return $tz;
    }
    return app_default_timezone();
}

function user_timezone_object(): DateTimeZone {
    return new DateTimeZone(user_timezone());
}

// Helper function to convert local datetime input to UTC format for database storage.
// It returns null if the input is empty or invalid.
function parse_local_datetime(?string $value, DateTimeZone $localTz, DateTimeZone $utcTz): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value, $localTz);
    if (!$dt) {
        return null;
    }

    $dt->setTimezone($utcTz);
    return $dt->format('Y-m-d H:i:s');
}

function formatLocalTime($utc_time, $format = 'Y-m-d\TH:i') {
    if (!$utc_time) return '';
    $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
    $dt->setTimezone(user_timezone_object());
    return $dt->format($format);
}

function formatLocalTimeRecentEntries($utc_time, $format = 'M j, Y g:i A') {
    if (!$utc_time) return '';
    $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
    $dt->setTimezone(user_timezone_object());
    return $dt->format($format);
}

function fmt_dur($secs) {
    if (!$secs) return "0:00";
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    $s = $secs % 60;
    return $h > 0 ? sprintf("%d:%02d:%02d", $h, $m, $s) : sprintf("%d:%02d", $m, $s);
}

function entry_project_label(array $row): string {
    $client = trim((string)($row['client_name'] ?? ''));
    $project = trim((string)($row['project_name'] ?? ''));
    $legacy = trim((string)($row['legacy_job_name'] ?? ''));

    if ($client !== '' && $project !== '') return $client . ' — ' . $project;
    if ($project !== '') return $project;
    if ($legacy !== '') return $legacy;
    return '—';
}

function entry_category_label(array $row): string {
    $category = trim((string)($row['category_name'] ?? ''));
    return $category !== '' ? $category : '—';
}

function buildTimeRecordsQuery($filters = []) {
    $sql = "SELECT
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
            WHERE tr.user_id = :user_id";

    $params = [':user_id' => $filters['user_id'] ?? user_id()];

    if (!empty($filters['project_name'])) {
        $sql .= " AND p.name LIKE :project_name";
        $params[':project_name'] = "%{$filters['project_name']}%";
    }

    if (!empty($filters['client_name'])) {
        $sql .= " AND cl.name LIKE :client_name";
        $params[':client_name'] = "%{$filters['client_name']}%";
    }

    if (!empty($filters['category_name'])) {
        $sql .= " AND wc.name LIKE :category_name";
        $params[':category_name'] = "%{$filters['category_name']}%";
    }

    if (!empty($filters['start_date'])) {
        $sql .= " AND tr.start_time >= :start";
        $params[':start'] = $filters['start_date'] . ' 00:00:00';
    }
    if (!empty($filters['end_date'])) {
        $sql .= " AND tr.start_time <= :end";
        $params[':end'] = $filters['end_date'] . ' 23:59:59';
    }

    if (isset($filters['limit']) && isset($filters['offset'])) {
        $sql .= " ORDER BY tr.start_time DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = (int)$filters['limit'];
        $params[':offset'] = (int)$filters['offset'];
    } else {
        $sql .= " ORDER BY tr.start_time DESC";
    }

    return [$sql, $params];
}

function executeQuery($sql, $params) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if (in_array($key, [':limit', ':offset'], true)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sanitizeFilename(string $s): string {
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $s);
}


function clear_auth_session(): void {
    $_SESSION = [];
    refresh_session_security();
}

function is_admin_user(): bool {
    return (int)($_SESSION['user']['id'] ?? 0) === 1;
}

function require_admin(): void {
    require_login();
    if (!is_admin_user()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function write_php_array_file(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
        throw new RuntimeException('Could not create target directory.');
    }

    $php = "<?php\nreturn " . var_export($data, true) . ";\n";
    if (file_put_contents($path, $php, LOCK_EX) === false) {
        throw new RuntimeException('Could not write file: ' . basename($path));
    }

    @chmod($path, 0600);
}

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function app_setup_required(PDO $pdo): bool {
    if (!table_exists($pdo, 'users')) {
        return true;
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    return $count === 0;
}

function tt_mail_config(): array {
    static $mailConfig = null;
    if ($mailConfig !== null) {
        return $mailConfig;
    }

    $cfg = require __DIR__ . '/config.php';
    $mailConfig = $cfg['mail'] ?? [];

    $mailConfig['host'] = (string)($mailConfig['host'] ?? '');
    $mailConfig['username'] = (string)($mailConfig['username'] ?? '');
    $mailConfig['password'] = (string)($mailConfig['password'] ?? '');
    $mailConfig['port'] = (int)($mailConfig['port'] ?? 465);
    $mailConfig['from_email'] = (string)($mailConfig['from_email'] ?? '');
    $mailConfig['from_name'] = (string)($mailConfig['from_name'] ?? 'Time Tracker');
    $mailConfig['encryption'] = strtolower((string)($mailConfig['encryption'] ?? 'ssl'));
    $mailConfig['phpmailer_path'] = rtrim((string)($mailConfig['phpmailer_path'] ?? ''), '/');

    return $mailConfig;
}

function tt_require_phpmailer(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $mailConfig = tt_mail_config();
    $basePath = rtrim((string)($mailConfig['phpmailer_path'] ?? ''), '/');
    if ($basePath === '') {
        throw new RuntimeException('PHPMailer path is not configured.');
    }

    $required = [
        $basePath . '/src/PHPMailer.php',
        $basePath . '/src/SMTP.php',
        $basePath . '/src/Exception.php',
    ];

    foreach ($required as $file) {
        if (!is_file($file)) {
            throw new RuntimeException('Missing PHPMailer file: ' . $file);
        }
        require_once $file;
    }

    $loaded = true;
}


function validate_password_strength(string $password): ?string {
    if (strlen($password) < 12) {
        return 'Password must be at least 12 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must include at least one special character.';
    }

    return null;
}

function password_reset_expires_minutes(): int {
    $cfg = require __DIR__ . '/config.php';
    $minutes = (int)($cfg['auth']['password_reset_expires_minutes'] ?? 60);
    return $minutes > 0 ? $minutes : 60;
}

function invalidate_password_reset_tokens(PDO $pdo, int $userId): void {
    if ($userId <= 0) {
        return;
    }

    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
}

function issue_password_reset_token(PDO $pdo, string $email): void {
    $email = trim(strtolower($email));
    if ($email === '') {
        return;
    }

    $stmt = $pdo->prepare('SELECT id, email, display_name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return;
    }

    $rawToken = token64();
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))
        ->modify('+' . password_reset_expires_minutes() . ' minutes')
        ->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        invalidate_password_reset_tokens($pdo, (int)$user['id']);
        $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $insert->execute([(int)$user['id'], $tokenHash, $expiresAt]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $resetUrl = base_url('/auth/reset_password.php?token=' . urlencode($rawToken));
    send_password_reset_email((string)$user['email'], (string)($user['display_name'] ?? ''), $resetUrl);
}

function get_password_reset_record(PDO $pdo, string $rawToken): ?array {
    $rawToken = trim($rawToken);
    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);
    $stmt = $pdo->prepare(
        'SELECT prt.id, prt.user_id, prt.expires_at, prt.used_at, u.email, u.display_name
         FROM password_reset_tokens prt
         INNER JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }

    $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
    $expiresUtc = new DateTime($row['expires_at'], new DateTimeZone('UTC'));
    if ($expiresUtc < $nowUtc) {
        return null;
    }

    return $row;
}

function reset_user_password(PDO $pdo, string $rawToken, string $newPassword): bool {
    $record = get_password_reset_record($pdo, $rawToken);
    if (!$record) {
        return false;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?')
            ->execute([$passwordHash, (int)$record['user_id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP() WHERE id = ?')
            ->execute([(int)$record['id']]);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND id <> ?')
            ->execute([(int)$record['user_id'], (int)$record['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return true;
}

function send_password_reset_email(string $toEmail, string $displayName, string $resetUrl): void {
    tt_require_phpmailer();

    $mailConfig = tt_mail_config();
    if (($mailConfig['host'] ?? '') === '' || ($mailConfig['username'] ?? '') === '' || ($mailConfig['from_email'] ?? '') === '') {
        throw new RuntimeException('Mail settings are incomplete.');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string)$mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$mailConfig['username'];
    $mail->Password = (string)($mailConfig['password'] ?? '');

    if (($mailConfig['encryption'] ?? 'ssl') === 'tls') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    }

    $mail->Port = (int)($mailConfig['port'] ?? 465);
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string)$mailConfig['from_email'], (string)($mailConfig['from_name'] ?? 'Time Tracker'));
    $mail->addAddress($toEmail, trim($displayName) !== '' ? $displayName : $toEmail);
    $mail->isHTML(true);
    $mail->Subject = 'Reset your Time Tracker password';

    $safeUrl = h($resetUrl);
    $safeName = h(trim($displayName) !== '' ? $displayName : 'there');
    $expires = password_reset_expires_minutes();

    $mail->Body = "
        <html>
          <body style=\"font-family: Arial, Helvetica, sans-serif; background-color: #f8f9fa; padding: 20px; color: #212529;\">
            <div style=\"max-width: 600px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 10px; border: 1px solid #e9ecef;\">
              <h2 style=\"margin-top: 0;\">Reset your password</h2>
              <p>Hello {$safeName},</p>
              <p>We received a request to reset the password for your Time Tracker account.</p>
              <p style=\"margin: 28px 0;\">
                <a href=\"{$safeUrl}\" style=\"display: inline-block; background: #0d6efd; color: #ffffff; text-decoration: none; padding: 12px 18px; border-radius: 6px;\">Reset Password</a>
              </p>
              <p>This link will expire in {$expires} minutes.</p>
              <p>If you did not request a password reset, you can safely ignore this email.</p>
              <hr style=\"border: 0; border-top: 1px solid #e9ecef; margin: 24px 0;\">
              <p style=\"font-size: 12px; color: #6c757d; word-break: break-all;\">If the button does not work, copy and paste this URL into your browser:<br>{$safeUrl}</p>
            </div>
          </body>
        </html>
    ";

    $mail->AltBody = "Reset your Time Tracker password\n\n" .
        "Use the link below to reset your password:\n{$resetUrl}\n\n" .
        "This link expires in {$expires} minutes. If you did not request this, you can ignore this email.";

    $mail->send();
}

function create_default_user_workspace(PDO $pdo, int $userId): void {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid user id.');
    }

    $defaultClientName = 'Sample Client';
    $defaultProjectName = 'Website Refresh';
    $defaultCategories = [
        'Planning',
        'Design',
        'Development',
        'Meetings',
        'Admin',
    ];

    $clientStmt = $pdo->prepare('INSERT INTO clients (user_id, name, is_active) VALUES (?, ?, 1)');
    $clientStmt->execute([$userId, $defaultClientName]);
    $clientId = (int)$pdo->lastInsertId();

    $projectStmt = $pdo->prepare('INSERT INTO projects (user_id, client_id, name, is_active) VALUES (?, ?, ?, 1)');
    $projectStmt->execute([$userId, $clientId, $defaultProjectName]);

    $categoryStmt = $pdo->prepare('INSERT INTO work_categories (user_id, name, is_active) VALUES (?, ?, 1)');
    foreach ($defaultCategories as $categoryName) {
        $categoryStmt->execute([$userId, $categoryName]);
    }
}

function delete_user_account(PDO $pdo, int $userId): void {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid user id.');
    }

    $stmt = $pdo->prepare('SELECT avatar_path FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('User not found.');
    }
    $avatarPath = trim((string)($row['avatar_path'] ?? ''));

    $tableHasUserId = static function (string $table) use ($pdo): bool {
        $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?';
        $check = $pdo->prepare($sql);
        $check->execute([$table, 'user_id']);
        return (int)$check->fetchColumn() > 0;
    };

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM time_records WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM projects WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM clients WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM work_categories WHERE user_id = ?')->execute([$userId]);

        foreach (['jobs', 'report_links'] as $optionalTable) {
            if ($tableHasUserId($optionalTable)) {
                $pdo->prepare("DELETE FROM {$optionalTable} WHERE user_id = ?")->execute([$userId]);
            }
        }

        $pdo->prepare('DELETE FROM users WHERE id = ? LIMIT 1')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($avatarPath !== '') {
        $avatarFullPath = avatar_file_path($avatarPath);
        if ($avatarFullPath !== null && is_file($avatarFullPath)) {
            @unlink($avatarFullPath);
        }
    }
}
