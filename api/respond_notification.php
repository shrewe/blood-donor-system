<?php
// api/respond_notification.php  –  AJAX endpoint
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'donor') {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$notifId   = (int)($body['notif_id']   ?? 0);
$action    = $body['action']    ?? '';
$requestId = (int)($body['request_id'] ?? 0);
$csrf      = $body['csrf']      ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

if (!in_array($action, ['accepted', 'rejected'])) {
    jsonResponse(['error' => 'Invalid action'], 400);
}

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

// Verify this notification belongs to this donor
$stmt = $pdo->prepare('SELECT n.id, d.id AS donor_id FROM notifications n JOIN donors d ON d.id = n.donor_id WHERE n.id = ? AND d.user_id = ?');
$stmt->execute([$notifId, $userId]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['error' => 'Notification not found'], 404);
}

$donorId = (int) $row['donor_id'];

// Update notification status
$pdo->prepare('UPDATE notifications SET status = ? WHERE id = ?')->execute([$action, $notifId]);

$contact = null;

if ($action === 'accepted') {
    // Increment SOS responses
    $pdo->prepare('UPDATE donors SET sos_responses = sos_responses + 1 WHERE id = ?')->execute([$donorId]);

    // Recalculate score
    $donor = $pdo->prepare('SELECT total_donations, sos_responses FROM donors WHERE id = ?');
    $donor->execute([$donorId]);
    $d = $donor->fetch();
    $score = computeReliabilityScore((int)$d['total_donations'], (int)$d['sos_responses']);
    $pdo->prepare('UPDATE donors SET reliability_score = ? WHERE id = ?')->execute([$score, $donorId]);

    // Fetch contact from emergency request
    if ($requestId) {
        $reqStmt = $pdo->prepare('SELECT contact_number FROM emergency_requests WHERE id = ?');
        $reqStmt->execute([$requestId]);
        $req = $reqStmt->fetch();
        $contact = $req['contact_number'] ?? null;

        // Create a pending donation record. If the SOS was raised by a hospital,
        // attach it to that hospital so it appears on the hospital dashboard for confirmation.
        $hospitalId = null;
        $reqOwner = $pdo->prepare('SELECT er.requested_by, u.role FROM emergency_requests er JOIN users u ON u.id = er.requested_by WHERE er.id = ?');
        $reqOwner->execute([$requestId]);
        $owner = $reqOwner->fetch();
        if ($owner && ($owner['role'] ?? '') === 'hospital') {
            $hospitalId = (int)$owner['requested_by'];
        }

        $ins = $pdo->prepare('INSERT INTO donations (donor_id, hospital_id, request_id, date, status) VALUES (?,?,?,?,?)');
        $ins->execute([$donorId, $hospitalId, $requestId, date('Y-m-d'), 'pending']);
    }
}

jsonResponse(['success' => true, 'action' => $action, 'contact' => $contact]);
