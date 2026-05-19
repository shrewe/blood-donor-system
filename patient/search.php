<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

// Search is accessible without login (read-only)
$pdo = getDB();

$bloodGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$selectedBG  = $_GET['blood_group'] ?? '';
$selectedCity= trim($_GET['city'] ?? '');
$onlyAvail   = isset($_GET['only_available']) ? (int)$_GET['only_available'] : 1;

// Build query
$where  = ['1=1'];
$params = [];

if (in_array($selectedBG, $bloodGroups)) {
    $where[]  = 'd.blood_group = ?';
    $params[] = $selectedBG;
}
if ($selectedCity !== '') {
    $where[]  = 'd.city LIKE ?';
    $params[] = '%' . $selectedCity . '%';
}
if ($onlyAvail) {
    $where[] = "d.availability_status != 'not_available'";
}

$sql = '
    SELECT d.*, u.name, u.phone, u.email
    FROM donors d
    JOIN users u ON u.id = d.user_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY
        CASE d.availability_status WHEN "available_now" THEN 0 WHEN "available_later" THEN 1 ELSE 2 END,
        d.reliability_score DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donors = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
  <h2>Find Blood Donors</h2>
  <p>Search by blood group or city. Available donors appear first.</p>
</div>

<!-- Search bar -->
<form method="GET" class="search-bar fade-in">
  <div class="form-group" style="margin-bottom:0;flex:1;min-width:140px;">
    <label style="margin-bottom:.3rem;">Blood Group</label>
    <select name="blood_group" class="form-control">
      <option value="">All Groups</option>
      <?php foreach ($bloodGroups as $bg): ?>
        <option value="<?= $bg ?>" <?= $selectedBG === $bg ? 'selected' : '' ?>><?= $bg ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="margin-bottom:0;flex:2;min-width:180px;">
    <label style="margin-bottom:.3rem;">City</label>
    <input type="text" name="city" class="form-control" placeholder="e.g. Goa, Mumbai" value="<?= sanitize($selectedCity) ?>">
  </div>
  <div class="form-group" style="margin-bottom:0;display:flex;align-items:flex-end;gap:.5rem;">
    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;color:var(--text-muted);font-size:.85rem;">
      <input type="checkbox" name="only_available" value="1" <?= $onlyAvail ? 'checked' : '' ?>>
      Available only
    </label>
  </div>
  <div style="display:flex;align-items:flex-end;">
    <button type="submit" class="btn btn-primary">Search</button>
  </div>
</form>

<!-- Live filter -->
<?php if (!empty($donors)): ?>
<div style="margin-bottom:1rem;">
  <input type="text" id="live-search" class="form-control" placeholder="🔍 Filter results by name or city..." style="max-width:380px;">
</div>
<?php endif; ?>

<!-- Results -->
<div id="donor-results">
<?php if (empty($donors)): ?>
  <div class="empty-state fade-in">
    <div class="empty-icon">🩸</div>
    <p>No donors found matching your criteria. Try broadening your search.</p>
    <?php if (isLoggedIn()): ?>
      <a href="<?= APP_URL ?>/patient/emergency.php" class="btn btn-sos" style="margin-top:1rem;">🚨 Send Emergency SOS</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p class="text-muted" style="margin-bottom:1rem;font-size:.88rem;"><?= count($donors) ?> donor<?= count($donors) !== 1 ? 's' : '' ?> found</p>
  <div style="display:flex;flex-direction:column;gap:.9rem;">
    <?php foreach ($donors as $d): ?>
    <div class="donor-card donor-card-clickable fade-in" data-href="<?= APP_URL ?>/patient/donor_profile.php?id=<?= (int)$d['id'] ?>" title="Click to view donor profile">
      <div class="blood-pill"><?= sanitize($d['blood_group']) ?></div>
      <div class="donor-info">
        <div class="donor-name"><?= sanitize($d['name']) ?></div>
        <div class="donor-meta">
          📍 <?= sanitize($d['city']) ?>
          &nbsp;·&nbsp;
          ⭐ Score: <?= (int)$d['reliability_score'] ?>
          &nbsp;·&nbsp;
          💉 Donations: <?= (int)$d['total_donations'] ?>
          <?php if ($d['last_donation_date']): ?>
            &nbsp;·&nbsp; Last: <?= date('d M Y', strtotime($d['last_donation_date'])) ?>
          <?php endif; ?>
        </div>
        <div class="donor-actions">
          <span class="status-badge status-<?= $d['availability_status'] ?>">
            <?= str_replace('_', ' ', ucfirst($d['availability_status'])) ?>
          </span>
          <?php if (isLoggedIn() && $d['availability_status'] === 'available_now'): ?>
            <?php
              $cleanPhone = preg_replace('/\D+/', '', $d['phone'] ?? '');
              $waPhone = $cleanPhone;
              if ($waPhone !== '' && strlen($waPhone) === 10) { $waPhone = '91' . $waPhone; }
              $waMsg = rawurlencode('Hello ' . $d['name'] . ', I found your profile on LifeLink Blood Donor Finder. I need ' . $d['blood_group'] . ' blood. Can you please help?');
            ?>
            <?php if ($cleanPhone): ?>
              <a class="btn btn-primary btn-sm" href="https://wa.me/<?= sanitize($waPhone) ?>?text=<?= $waMsg ?>" target="_blank" rel="noopener">💬 Contact Him</a>
            <?php endif; ?>
            <span class="text-muted" style="font-size:.82rem;display:flex;align-items:center;gap:.3rem;">
              📞 <?= sanitize($d['phone'] ?? 'Contact via request') ?>
            </span>
          <?php elseif (!isLoggedIn()): ?>
            <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-sm">Login to contact</a>
          <?php endif; ?>
          <span class="view-profile-hint">View Profile →</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<!-- SOS CTA at bottom -->
<div class="sos-panel fade-in" style="margin-top:3rem;">
  <div class="sos-icon">🚨</div>
  <h3>Need blood urgently?</h3>
  <p class="text-muted" style="margin-bottom:1.5rem;">Trigger an emergency SOS to instantly alert all nearby compatible donors.</p>
  <a href="<?= APP_URL ?>/patient/emergency.php" class="btn btn-sos">Request Emergency Blood</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
