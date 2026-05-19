<?php
// api/confirm_donation.php  –  AJAX endpoint (hospital only)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'hospital') {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$donationId = (int)($body['donation_id'] ?? 0);
$csrf       = $body['csrf'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

// Verify donation belongs to this hospital and is pending
$stmt = $pdo->prepare('SELECT d.id, d.donor_id, d.date FROM donations d WHERE d.id = ? AND d.hospital_id = ? AND d.status = "pending"');
$stmt->execute([$donationId, $userId]);
$donation = $stmt->fetch();

if (!$donation) {
    jsonResponse(['error' => 'Donation not found or already confirmed'], 404);
}

$pdo->beginTransaction();
try {
    // Mark donation confirmed
    $pdo->prepare('UPDATE donations SET status = "confirmed" WHERE id = ?')->execute([$donationId]);

    // Update donor: last_donation_date + increment total_donations
    $pdo->prepare('UPDATE donors SET last_donation_date = ?, total_donations = total_donations + 1 WHERE id = ?')
        ->execute([$donation['date'], $donation['donor_id']]);

    // Recalculate reliability score
    $dRow = $pdo->prepare('SELECT total_donations, sos_responses FROM donors WHERE id = ?');
    $dRow->execute([$donation['donor_id']]);
    $d = $dRow->fetch();
    $score = computeReliabilityScore((int)$d['total_donations'], (int)$d['sos_responses']);
    $pdo->prepare('UPDATE donors SET reliability_score = ? WHERE id = ?')->execute([$score, $donation['donor_id']]);

    // Auto-set availability to not_available (just donated)
    $pdo->prepare('UPDATE donors SET availability_status = "not_available" WHERE id = ?')->execute([$donation['donor_id']]);

    $pdo->commit();
    // Fresh stats for the dashboard tiles
    $stats = $pdo->prepare('SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status="confirmed" THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN status="pending" THEN 1 ELSE 0 END) AS pending
        FROM donations WHERE hospital_id = ?');
    $stats->execute([$userId]);
    $statRow = $stats->fetch() ?: ['total' => 0, 'confirmed' => 0, 'pending' => 0];

    jsonResponse([
        'success' => true,
        'score' => $score,
        'stats' => [
            'total' => (int)$statRow['total'],
            'confirmed' => (int)$statRow['confirmed'],
            'pending' => (int)$statRow['pending'],
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Failed to confirm donation'], 500);
}
