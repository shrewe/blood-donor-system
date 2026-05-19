<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
requireRole('donor');

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT d.*, u.name, u.email, u.phone FROM donors d JOIN users u ON u.id = d.user_id WHERE d.user_id = ?');
$stmt->execute([$userId]);
$donor = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city  = trim($_POST['city']  ?? '');
    $bg    = $_POST['blood_group'] ?? '';
    $lat   = is_numeric($_POST['lat'] ?? '') ? (float)$_POST['lat'] : null;
    $lng   = is_numeric($_POST['lng'] ?? '') ? (float)$_POST['lng'] : null;
    $lastDate = $_POST['last_donation_date'] ?? '';

    $bloodGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    if (strlen($name) < 2) $errors[] = 'Name too short.';
    if (!in_array($bg, $bloodGroups)) $errors[] = 'Invalid blood group.';
    if (empty($city)) $errors[] = 'City required.';

    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET name=?, phone=? WHERE id=?')->execute([$name, $phone, $userId]);
        $pdo->prepare('UPDATE donors SET blood_group=?, city=?, latitude=?, longitude=?, last_donation_date=? WHERE user_id=?')
            ->execute([$bg, $city, $lat, $lng, $lastDate ?: null, $userId]);

        // Recalculate score
        $score = computeReliabilityScore((int)$donor['total_donations'], (int)$donor['sos_responses']);
        $pdo->prepare('UPDATE donors SET reliability_score=? WHERE user_id=?')->execute([$score, $userId]);

        setFlash('success', 'Profile updated successfully.');
        header('Location: ' . APP_URL . '/donor/dashboard.php'); exit;
    }
    // Overlay POST values over $donor for re-display
    $donor['name'] = $name; $donor['phone'] = $phone; $donor['city'] = $city;
    $donor['blood_group'] = $bg; $donor['latitude'] = $lat; $donor['longitude'] = $lng;
    $donor['last_donation_date'] = $lastDate;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2>Edit Profile</h2>
  <p>Update your donor information</p>
</div>

<div class="card fade-in" style="max-width:600px;">
  <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

    <div class="form-row">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" class="form-control" value="<?= sanitize($donor['name']) ?>" required>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="phone" class="form-control" value="<?= sanitize($donor['phone'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Blood Group</label>
        <select name="blood_group" class="form-control">
          <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
            <option value="<?= $bg ?>" <?= $donor['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>City</label>
        <input type="text" name="city" class="form-control" value="<?= sanitize($donor['city']) ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Latitude (GPS)</label>
        <input type="number" step="any" name="lat" class="form-control" id="lat-field" value="<?= $donor['latitude'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>Longitude (GPS)</label>
        <input type="number" step="any" name="lng" class="form-control" id="lng-field" value="<?= $donor['longitude'] ?? '' ?>">
      </div>
    </div>
    <button type="button" class="btn btn-outline btn-sm mb-2" onclick="getGPS()">📍 Auto-detect GPS</button>

    <div class="form-group">
      <label>Last Donation Date</label>
      <input type="date" name="last_donation_date" class="form-control"
             value="<?= sanitize($donor['last_donation_date'] ?? '') ?>"
             max="<?= date('Y-m-d') ?>">
    </div>

    <div style="display:flex;gap:.75rem;margin-top:1rem;">
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="<?= APP_URL ?>/donor/dashboard.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<script>
function getGPS() {
  if (!navigator.geolocation) return alert('Geolocation not supported');
  navigator.geolocation.getCurrentPosition(p => {
    document.getElementById('lat-field').value = p.coords.latitude.toFixed(7);
    document.getElementById('lng-field').value = p.coords.longitude.toFixed(7);
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
