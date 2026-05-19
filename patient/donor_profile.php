<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDB();
$donorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('
    SELECT d.*, u.name, u.email, u.phone, u.created_at
    FROM donors d
    JOIN users u ON u.id = d.user_id
    WHERE d.id = ?
    LIMIT 1
');
$stmt->execute([$donorId]);
$donor = $stmt->fetch();

if (!$donor) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state fade-in"><div class="empty-icon">🩸</div><p>Donor profile not found.</p><a class="btn btn-primary" href="' . APP_URL . '/patient/search.php">Back to Search</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$hist = $pdo->prepare('SELECT date, status, notes FROM donations WHERE donor_id = ? ORDER BY date DESC LIMIT 5');
$hist->execute([$donorId]);
$donations = $hist->fetchAll();

$statusText = ucwords(str_replace('_', ' ', $donor['availability_status']));
$canContact = isLoggedIn() && $donor['availability_status'] === 'available_now';
$cleanPhone = preg_replace('/\D+/', '', $donor['phone'] ?? '');
$whatsappPhone = $cleanPhone;
if ($whatsappPhone !== '' && strlen($whatsappPhone) === 10) {
    $whatsappPhone = '91' . $whatsappPhone;
}
$whatsappMsg = rawurlencode('Hello ' . $donor['name'] . ', I found your profile on LifeLink Blood Donor Finder. I need ' . $donor['blood_group'] . ' blood. Can you please help?');

include __DIR__ . '/../includes/header.php';
?>

<div class="profile-shell fade-in">
  <a href="<?= APP_URL ?>/patient/search.php" class="btn btn-outline btn-sm" style="margin-bottom:1rem;">← Back to Donors</a>

  <div class="donor-profile-hero">
    <div class="blood-pill profile-blood"><?= sanitize($donor['blood_group']) ?></div>
    <div class="profile-main">
      <h2><?= sanitize($donor['name']) ?></h2>
      <p class="text-muted">📍 <?= sanitize($donor['city']) ?> &nbsp;·&nbsp; Member since <?= date('M Y', strtotime($donor['created_at'])) ?></p>
      <div class="donor-actions" style="margin-top:1rem;">
        <span class="status-badge status-<?= sanitize($donor['availability_status']) ?>"><?= sanitize($statusText) ?></span>
        <?php if ($canContact): ?>
          <?php if ($cleanPhone): ?>
            <a class="btn btn-primary btn-sm" href="https://wa.me/<?= sanitize($whatsappPhone) ?>?text=<?= $whatsappMsg ?>" target="_blank" rel="noopener">💬 Contact Him</a>
            <a class="btn btn-outline btn-sm" href="tel:<?= sanitize($cleanPhone) ?>">📞 Call</a>
          <?php endif; ?>
          <span class="contact-chip">📞 <?= sanitize($donor['phone'] ?: 'Not provided') ?></span>
          <span class="contact-chip">✉️ <?= sanitize($donor['email']) ?></span>
        <?php elseif (!isLoggedIn()): ?>
          <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-primary btn-sm">Login to Contact</a>
        <?php else: ?>
          <span class="text-muted">Contact is shown only when donor is available now.</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="grid-3" style="margin-top:1.5rem;">
    <div class="stat-tile"><div class="stat-num"><?= (int)$donor['reliability_score'] ?></div><div class="stat-label">Reliability Score</div></div>
    <div class="stat-tile"><div class="stat-num"><?= (int)$donor['total_donations'] ?></div><div class="stat-label">Total Donations</div></div>
    <div class="stat-tile"><div class="stat-num"><?= (int)$donor['sos_responses'] ?></div><div class="stat-label">SOS Responses</div></div>
  </div>

  <div class="grid-2" style="margin-top:1.5rem;">
    <div class="card">
      <h3 class="card-title">Donor Details</h3>
      <p><strong>Blood Group:</strong> <?= sanitize($donor['blood_group']) ?></p>
      <p><strong>City:</strong> <?= sanitize($donor['city']) ?></p>
      <p><strong>Last Donation:</strong> <?= $donor['last_donation_date'] ? date('d M Y', strtotime($donor['last_donation_date'])) : 'Not available' ?></p>
      <p><strong>Availability:</strong> <?= sanitize($statusText) ?></p>
    </div>

    <div class="card">
      <h3 class="card-title">Recent Donation History</h3>
      <?php if (empty($donations)): ?>
        <p class="text-muted">No donation history found.</p>
      <?php else: ?>
        <?php foreach ($donations as $dn): ?>
          <div class="history-row">
            <span><?= date('d M Y', strtotime($dn['date'])) ?></span>
            <span class="status-badge status-<?= sanitize($dn['status']) ?>"><?= sanitize(ucfirst($dn['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
