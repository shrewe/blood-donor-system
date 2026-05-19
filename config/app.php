<?php
// ============================================================
// config/app.php  –  Application-wide constants
// ============================================================

define('APP_NAME',    'LifeLink – Blood Donor Finder');
// Base URL: edit APP_URL_OVERRIDE only if your folder name is different.
// Examples: 'http://localhost/blood-donor-system' or 'http://localhost/blood-donor-system-main'
if (!defined('APP_URL_OVERRIDE')) {
    define('APP_URL_OVERRIDE', '');
}
if (APP_URL_OVERRIDE !== '') {
    define('APP_URL', rtrim(APP_URL_OVERRIDE, '/'));
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $parts  = explode('/', trim($script, '/'));
    $root   = $parts[0] ?? 'blood-donor-system';
    define('APP_URL', $scheme . '://' . $host . '/' . $root);
}
define('SESSION_NAME','bds_session');

// Donation eligibility gap in days
define('DONATION_GAP_DAYS', 90);

// SOS notification radius (km) – used with Haversine formula
define('SOS_RADIUS_KM', 50);

// Reliability score weights
define('SCORE_PER_DONATION', 10);
define('SCORE_PER_SOS_RESPONSE', 5);

// Email (for simulated alerts – use PHPMailer in production)
define('ADMIN_EMAIL', 'admin@lifelink.local');

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,  // set true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
