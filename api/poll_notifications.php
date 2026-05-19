<?php
// api/poll_notifications.php  –  lightweight polling endpoint
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'donor') {
    jsonResponse(['count' => 0]);
}

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id FROM donors WHERE user_id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['count' => 0]);
}

$count = countUnreadNotifications((int)$row['id']);
jsonResponse(['count' => $count]);
