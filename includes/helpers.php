<?php
// ============================================================
// includes/helpers.php  –  Utility functions
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

// ── Auth helpers ─────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/auth/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . $redirect);
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . APP_URL . '/index.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? 0,
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['role']      ?? '',
    ];
}

// ── Input / Security helpers ─────────────────────────────────

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── Distance (Haversine) ──────────────────────────────────────

function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R   = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a   = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * asin(sqrt($a));
}

// ── Donor helpers ─────────────────────────────────────────────

function isDonationEligible(?string $lastDate): bool {
    if (!$lastDate) return true;
    $diff = (new DateTime())->diff(new DateTime($lastDate));
    return $diff->days >= DONATION_GAP_DAYS;
}

function computeReliabilityScore(int $donations, int $sosResponses): int {
    return ($donations * SCORE_PER_DONATION) + ($sosResponses * SCORE_PER_SOS_RESPONSE);
}

// ── Notification helpers ──────────────────────────────────────

function countUnreadNotifications(int $donorId): int {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE donor_id = ? AND status = "unread"');
    $stmt->execute([$donorId]);
    return (int) $stmt->fetchColumn();
}

function createNotification(int $donorId, string $message, ?int $requestId = null): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare('INSERT INTO notifications (donor_id, request_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$donorId, $requestId, $message]);
}

// ── Flash messages ────────────────────────────────────────────

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
