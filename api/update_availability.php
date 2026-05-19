<?php
// api/update_availability.php  –  AJAX endpoint
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'donor') {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$status = $body['status'] ?? '';
$csrf   = $body['csrf']   ?? '';

// Validate CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

$allowed = ['available_now', 'available_later', 'not_available'];
if (!in_array($status, $allowed)) {
    jsonResponse(['error' => 'Invalid status'], 400);
}

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

// Check eligibility if trying to set "available_now"
if ($status === 'available_now') {
    $stmt = $pdo->prepare('SELECT last_donation_date FROM donors WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row  = $stmt->fetch();
    if ($row && !isDonationEligible($row['last_donation_date'])) {
        jsonResponse(['error' => 'You are not eligible to donate yet (90-day rule). Set to "Available Later" instead.'], 400);
    }
}

$pdo->prepare('UPDATE donors SET availability_status = ? WHERE user_id = ?')->execute([$status, $userId]);
jsonResponse(['success' => true, 'status' => $status]);
