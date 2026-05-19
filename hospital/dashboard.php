<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
requireRole('hospital');

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

// Hospital info
$hStmt = $pdo->prepare('SELECT h.*, u.name, u.email, u.phone FROM hospitals h JOIN users u ON u.id = h.user_id WHERE h.user_id = ?');
$hStmt->execute([$userId]);
$hospital = $hStmt->fetch();

// Stats
$stats = $pdo->prepare('SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status="confirmed" THEN 1 ELSE 0 END) AS confirmed,
    SUM(CASE WHEN status="pending"   THEN 1 ELSE 0 END) AS pending
    FROM donations WHERE hospital_id = ?');
$stats->execute([$userId]);
$statRow = $stats->fetch();

// Recent donations
$dStmt = $pdo->prepare('
    SELECT don.*, u.name AS donor_name, u.phone AS donor_phone, d.blood_group
    FROM donations don
    JOIN donors d ON d.id = don.donor_id
    JOIN users u ON u.id = d.user_id
    WHERE don.hospital_id = ?
    ORDER BY don.created_at DESC LIMIT 20
');
$dStmt->execute([$userId]);
$donations = $dStmt->fetchAll();

// Active SOS requests by this hospital
$sosStmt = $pdo->prepare("SELECT * FROM emergency_requests WHERE requested_by = ? AND status = 'active' ORDER BY created_at DESC LIMIT 5");
$sosStmt->execute([$userId]);
$activeRequests = $sosStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
  <h2><?= sanitize($hospital['name'] ?? 'Hospital Dashboard') ?></h2>
  <p><?= sanitize($hospital['city'] ?? '') ?> &nbsp;·&nbsp; <?= sanitize($hospital['address'] ?? '') ?></p>
</div>

<div class="grid-3 fade-in mb-2">
  <div class="stat-tile">
    <div class="stat-num" id="stat-total"><?= (int)$statRow['total'] ?></div>
    <div class="stat-label">Total Donations</div>
  </div>
  <div class="stat-tile">
    <div class="stat-num" id="stat-confirmed"><?= (int)$statRow['confirmed'] ?></div>
    <div class="stat-label">Confirmed</div>
  </div>
  <div class="stat-tile">
    <div class="stat-num" id="stat-pending"><?= (int)$statRow['pending'] ?></div>
    <div class="stat-label">Pending</div>
  </div>
</div>

<!-- Actions -->
<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:2rem;" class="fade-in">
  <a href="<?= APP_URL ?>/patient/search.php" class="btn btn-outline">🔍 Search Donors</a>
  <a href="<?= APP_URL ?>/patient/emergency.php" class="btn btn-primary">🚨 Emergency SOS</a>
  <a href="<?= APP_URL ?>/hospital/add_donation.php" class="btn btn-green">+ Record Donation</a>
</div>

<!-- Active SOS -->
<?php if (!empty($activeRequests)): ?>
<div class="card fade-in" style="border-color:rgba(224,49,49,.35);margin-bottom:2rem;">
  <div class="card-title" style="color:var(--red);">Active Emergency Requests</div>
  <?php foreach ($activeRequests as $r): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--border);">
      <div>
        <span class="blood-pill" style="width:36px;height:36px;font-size:.78rem;display:inline-flex;margin-right:.6rem;"><?= sanitize($r['blood_group']) ?></span>
        <?= sanitize($r['location']) ?>, <?= sanitize($r['city']) ?>
        <span class="text-muted" style="font-size:.8rem;margin-left:.5rem;"><?= date('d M H:i', strtotime($r['created_at'])) ?></span>
      </div>
      <span class="status-badge status-available_now">Active</span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Donations table -->
<div class="card fade-in">
  <div class="card-title">Donation Records</div>
  <?php if (empty($donations)): ?>
    <div class="empty-state"><div class="empty-icon">💉</div><p>No donations recorded yet. Use "Record Donation" to add one.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date</th><th>Donor</th><th>Blood</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($donations as $d): ?>
        <tr>
          <td><?= date('d M Y', strtotime($d['date'])) ?></td>
          <td><?= sanitize($d['donor_name']) ?><br><small class="text-muted"><?= sanitize($d['donor_phone'] ?? '') ?></small></td>
          <td><span class="blood-pill" style="width:36px;height:36px;font-size:.78rem;display:inline-flex;"><?= sanitize($d['blood_group']) ?></span></td>
          <td><span class="donation-status <?= $d['status'] === 'confirmed' ? 'text-green' : ($d['status'] === 'pending' ? '' : 'text-muted') ?>">
            <?= ucfirst($d['status']) ?></span></td>
          <td>
            <?php if ($d['status'] === 'pending'): ?>
              <button class="btn btn-green btn-sm confirm-donation-btn" data-id="<?= $d['id'] ?>">Confirm</button>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<input type="hidden" name="csrf_token" value="<?= csrf() ?>">

<?php include __DIR__ . '/../includes/footer.php'; ?>
