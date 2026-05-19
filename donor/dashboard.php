<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
requireRole('donor');

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

// Fetch donor profile
$stmt = $pdo->prepare('
    SELECT d.*, u.name, u.email, u.phone
    FROM donors d
    JOIN users u ON u.id = d.user_id
    WHERE d.user_id = ?
');
$stmt->execute([$userId]);
$donor = $stmt->fetch();

if (!$donor) {
    setFlash('error', 'Donor profile not found.');
    header('Location: ' . APP_URL . '/auth/logout.php'); exit;
}

$donorId   = (int) $donor['id'];
$eligible  = isDonationEligible($donor['last_donation_date']);

// Fetch recent donations (last 5)
$dStmt = $pdo->prepare('
    SELECT don.*, u.name AS hospital_name
    FROM donations don
    LEFT JOIN users u ON u.id = don.hospital_id
    WHERE don.donor_id = ?
    ORDER BY don.date DESC LIMIT 5
');
$dStmt->execute([$donorId]);
$recentDonations = $dStmt->fetchAll();

$notifCount = countUnreadNotifications($donorId);

include __DIR__ . '/../includes/header.php';
?>
<body data-role="donor">

<div class="page-header fade-in">
  <h2>Welcome, <?= sanitize($donor['name']) ?> 👋</h2>
  <p>Your donor dashboard – manage availability and track your impact.</p>
</div>

<!-- Stats row -->
<div class="grid-3 fade-in mb-2">
  <div class="stat-tile">
    <div class="stat-num"><?= (int)$donor['total_donations'] ?></div>
    <div class="stat-label">Total Donations</div>
  </div>
  <div class="stat-tile">
    <div class="stat-num"><?= (int)$donor['sos_responses'] ?></div>
    <div class="stat-label">SOS Responses</div>
  </div>
  <div class="stat-tile">
    <div class="stat-num"><?= (int)$donor['reliability_score'] ?></div>
    <div class="stat-label">Reliability Score</div>
  </div>
</div>

<div class="grid-2 fade-in">

  <!-- Profile card -->
  <div class="card">
    <div class="card-title">Your Profile</div>
    <div style="display:flex;align-items:center;gap:1.2rem;margin-bottom:1.2rem;">
      <div class="blood-pill"><?= sanitize($donor['blood_group']) ?></div>
      <div>
        <div style="font-weight:700;font-size:1.05rem;"><?= sanitize($donor['name']) ?></div>
        <div class="text-muted" style="font-size:.85rem;"><?= sanitize($donor['email']) ?></div>
        <div class="text-muted" style="font-size:.85rem;"><?= sanitize($donor['phone'] ?? '—') ?></div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;font-size:.88rem;">
      <div><span class="text-muted">City:</span> <?= sanitize($donor['city']) ?></div>
      <div><span class="text-muted">Blood Group:</span> <?= sanitize($donor['blood_group']) ?></div>
      <div><span class="text-muted">Last Donated:</span> <?= $donor['last_donation_date'] ? date('d M Y', strtotime($donor['last_donation_date'])) : 'Never' ?></div>
      <div><span class="text-muted">GPS:</span> <?= $donor['latitude'] ? number_format($donor['latitude'],4).','.number_format($donor['longitude'],4) : 'Not set' ?></div>
    </div>
    <hr class="divider">
    <a href="<?= APP_URL ?>/donor/profile.php" class="btn btn-outline btn-sm">Edit Profile</a>
  </div>

  <!-- Availability card -->
  <div class="card">
    <div class="card-title">Availability Status</div>

    <?php if (!$eligible): ?>
      <div class="ineligible-note" id="eligibility-note">
        ⚠️ You donated on <?= date('d M Y', strtotime($donor['last_donation_date'])) ?>. You can donate again after <?= DONATION_GAP_DAYS ?> days (<?= date('d M Y', strtotime($donor['last_donation_date'] . ' +' . DONATION_GAP_DAYS . ' days')) ?>).
      </div>
    <?php endif; ?>

    <p class="text-muted" style="font-size:.85rem;margin-bottom:1rem;">Let patients know when you can donate.</p>
    <div class="avail-toggle">
      <?php
      $statuses = [
          'available_now'   => '🟢 Available Now',
          'available_later' => '🟡 Available Later',
          'not_available'   => '⚫ Not Available',
      ];
      foreach ($statuses as $key => $label): ?>
        <button class="avail-btn <?= $donor['availability_status'] === $key ? 'active-'.$key : '' ?>"
                data-status="<?= $key ?>">
          <?= $label ?>
        </button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

    <hr class="divider">
    <div style="display:flex;align-items:center;gap:1rem;">
      <div class="score-ring">
        <span class="score-num"><?= (int)$donor['reliability_score'] ?></span>
        <span class="score-label">Score</span>
      </div>
      <div>
        <div style="font-weight:600;">Reliability Score</div>
        <div class="text-muted" style="font-size:.82rem;">Donations ×<?= SCORE_PER_DONATION ?> + SOS responses ×<?= SCORE_PER_SOS_RESPONSE ?></div>
      </div>
    </div>
  </div>

</div>

<!-- Notifications preview -->
<?php if ($notifCount > 0): ?>
<div class="card fade-in mt-3" style="border-color:rgba(224,49,49,.4);">
  <div class="card-title" style="color:var(--red);">🚨 You have <?= $notifCount ?> unread emergency alert<?= $notifCount > 1 ? 's' : '' ?></div>
  <a href="<?= APP_URL ?>/donor/notifications.php" class="btn btn-primary btn-sm">View Alerts</a>
</div>
<?php endif; ?>

<!-- Donation history -->
<div class="card fade-in mt-3">
  <div class="card-title">Recent Donation History</div>
  <?php if ($recentDonations): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Hospital</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentDonations as $d): ?>
        <tr>
          <td><?= date('d M Y', strtotime($d['date'])) ?></td>
          <td><?= sanitize($d['hospital_name'] ?? 'Unspecified') ?></td>
          <td><span class="status-badge status-<?= $d['status'] === 'confirmed' ? 'available_now' : ($d['status'] === 'pending' ? 'available_later' : 'not_available') ?>">
            <?= ucfirst($d['status']) ?>
          </span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">💉</div><p>No donation history yet. Your first donation will appear here.</p></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
