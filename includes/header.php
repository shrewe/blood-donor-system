<?php
// includes/header.php  –  Shared HTML head + nav
require_once __DIR__ . '/../includes/helpers.php';
$flash    = getFlash();
$user     = currentUser();
$notifCount = 0;
if ($user['role'] === 'donor' && $user['id']) {
    // get donor id for this user
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id FROM donors WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $donorRow = $stmt->fetch();
    if ($donorRow) {
        $notifCount = countUnreadNotifications($donorRow['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?></title>
<meta name="app-url" content="<?= APP_URL ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body data-role="<?= sanitize($user['role'] ?? '') ?>">

<nav class="navbar">
  <a class="nav-brand" href="<?= APP_URL ?>">
    <span class="drop">&#9679;</span> LifeLink
  </a>
  <ul class="nav-links">
    <?php if (isLoggedIn()): ?>
      <?php if ($user['role'] === 'donor'): ?>
        <li><a href="<?= APP_URL ?>/donor/dashboard.php">Dashboard</a></li>
        <li><a href="<?= APP_URL ?>/donor/notifications.php">
          Alerts <?php if ($notifCount > 0): ?><span class="badge"><?= $notifCount ?></span><?php endif; ?>
        </a></li>
      <?php elseif ($user['role'] === 'patient'): ?>
        <li><a href="<?= APP_URL ?>/patient/search.php">Find Donors</a></li>
        <li><a href="<?= APP_URL ?>/patient/emergency.php">Emergency SOS</a></li>
      <?php elseif ($user['role'] === 'hospital'): ?>
        <li><a href="<?= APP_URL ?>/hospital/dashboard.php">Dashboard</a></li>
        <li><a href="<?= APP_URL ?>/patient/search.php">Search Donors</a></li>
        <li><a href="<?= APP_URL ?>/patient/emergency.php">Emergency SOS</a></li>
      <?php endif; ?>
      <li><a href="<?= APP_URL ?>/auth/logout.php" class="btn-nav-logout">Logout</a></li>
    <?php else: ?>
      <li><a href="<?= APP_URL ?>/auth/login.php">Login</a></li>
      <li><a href="<?= APP_URL ?>/auth/register.php" class="btn-nav">Register</a></li>
    <?php endif; ?>
  </ul>
</nav>

<main class="container">

<?php if ($flash): ?>
<div class="alert alert-<?= sanitize($flash['type']) ?>">
  <?= sanitize($flash['msg']) ?>
</div>
<?php endif; ?>
