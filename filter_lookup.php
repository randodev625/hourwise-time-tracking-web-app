<?php
require __DIR__ . '/middleware.php';
require_login();

header('Content-Type: application/json');

$user_id = user_id();
$type = $_GET['type'] ?? '';
$q = trim($_GET['q'] ?? '');

if (!in_array($type, ['project', 'client', 'category'], true)) {
    echo json_encode([]);
    exit;
}

switch ($type) {
    case 'project':
        if ($q === '') {
            $stmt = $pdo->prepare("
                SELECT CONCAT(c.name, ' — ', p.name) AS label
                FROM projects p
                JOIN clients c ON c.id = p.client_id
                WHERE p.user_id = ?
                  AND p.is_active = 1
                ORDER BY c.name, p.name
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
        } else {
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("
                SELECT CONCAT(c.name, ' — ', p.name) AS label
                FROM projects p
                JOIN clients c ON c.id = p.client_id
                WHERE p.user_id = ?
                  AND p.is_active = 1
                  AND (
                        p.name LIKE ?
                     OR c.name LIKE ?
                     OR CONCAT(c.name, ' — ', p.name) LIKE ?
                  )
                ORDER BY c.name, p.name
                LIMIT 10
            ");
            $stmt->execute([$user_id, $like, $like, $like]);
        }
        break;

    case 'client':
        if ($q === '') {
            $stmt = $pdo->prepare("
                SELECT name AS label
                FROM clients
                WHERE user_id = ?
                  AND is_active = 1
                ORDER BY name
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
        } else {
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("
                SELECT name AS label
                FROM clients
                WHERE user_id = ?
                  AND is_active = 1
                  AND name LIKE ?
                ORDER BY name
                LIMIT 10
            ");
            $stmt->execute([$user_id, $like]);
        }
        break;

    case 'category':
        if ($q === '') {
            $stmt = $pdo->prepare("
                SELECT name AS label
                FROM work_categories
                WHERE user_id = ?
                  AND is_active = 1
                ORDER BY name
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
        } else {
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("
                SELECT name AS label
                FROM work_categories
                WHERE user_id = ?
                  AND is_active = 1
                  AND name LIKE ?
                ORDER BY name
                LIMIT 10
            ");
            $stmt->execute([$user_id, $like]);
        }
        break;
}

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));