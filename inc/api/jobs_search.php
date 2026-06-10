<?php
require __DIR__ . '/../core/middleware.php';
require_login();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare('
    SELECT p.id, CONCAT(c.name, " — ", p.name) AS name
    FROM projects p
    JOIN clients c ON c.id = p.client_id
    WHERE p.user_id = ?
      AND p.is_active = 1
      AND (
        p.name LIKE ?
        OR c.name LIKE ?
        OR CONCAT(c.name, " — ", p.name) LIKE ?
      )
    ORDER BY c.name, p.name
    LIMIT 10
');
$like = '%' . $q . '%';
$stmt->execute([user_id(), $like, $like, $like]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($projects);
