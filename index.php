<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/helpers.php';
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'donor')    header('Location: ' . APP_URL . '/donor/dashboard.php');
    elseif ($role === 'hospital') header('Location: ' . APP_URL . '/hospital/dashboard.php');
    else header('Location: ' . APP_URL . '/patient/search.php');
    exit;
}
include __DIR__ . '/includes/header.php';
?>

<div class="hero fade-in">
  <p class="hero-eyebrow">Real-Time Blood Donor Network</p>
  <h1>Find Blood Donors<br><span>When Every Second Counts</span></h1>
  <p>LifeLink connects donors, patients, and hospitals instantly. Our emergency SOS system finds compatible donors nearby within minutes.</p>
  <div class="hero-actions">
    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary">Register as Donor</a>
    <a href="<?= APP_URL ?>/patient/search.php" class="btn btn-outline">Search Donors</a>
  </div>
</div>

<div class="features">
  <div class="feature-card fade-in">
    <div class="feature-icon">🩸</div>
    <h4>All Blood Groups</h4>
    <p>Find donors for A+, B+, O-, AB+ and all other blood types with a single search.</p>
  </div>
  <div class="feature-card fade-in">
    <div class="feature-icon">🚨</div>
    <h4>Emergency SOS</h4>
    <p>One-tap emergency alerts notify nearby compatible donors instantly during crises.</p>
  </div>
  <div class="feature-card fade-in">
    <div class="feature-icon">📍</div>
    <h4>Location Aware</h4>
    <p>GPS-based distance sorting shows the nearest available donors first.</p>
  </div>
  <div class="feature-card fade-in">
    <div class="feature-icon">⭐</div>
    <h4>Reliability Scores</h4>
    <p>Trust verified donors with scores based on donation history and SOS response rates.</p>
  </div>
</div>

<div style="text-align:center; margin-top: 5rem; padding: 3rem 0;">
  <p class="hero-eyebrow">Emergency?</p>
  <h2 style="font-family:var(--font-head);font-size:2rem;font-weight:800;margin-bottom:1.5rem;">Don't wait. Trigger an SOS now.</h2>
  <a href="<?= APP_URL ?>/patient/emergency.php" class="btn btn-sos">🚨 Request Emergency Blood</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
