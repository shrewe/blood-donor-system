<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

// Allow guests too, but must provide contact info
$pdo     = getDB();
$user    = currentUser();
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $bloodGroup  = $_POST['blood_group']  ?? '';
    $location    = trim($_POST['location']  ?? '');
    $city        = trim($_POST['city']      ?? '');
    $lat         = is_numeric($_POST['lat'] ?? '') ? (float)$_POST['lat'] : null;
    $lng         = is_numeric($_POST['lng'] ?? '') ? (float)$_POST['lng'] : null;
    $patientName = trim($_POST['patient_name'] ?? '');
    $contact     = trim($_POST['contact_number'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    $bloodGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    if (!in_array($bloodGroup, $bloodGroups)) $errors[] = 'Select a valid blood group.';
    if (empty($location))    $errors[] = 'Hospital / location is required.';
    if (empty($city))        $errors[] = 'City is required.';
    if (empty($contact))     $errors[] = 'Contact number is required.';

    // Need a user ID – if not logged in, create a guest placeholder or require login
    $requestedBy = $user['id'] ?: 0;
    if (!$requestedBy && !isLoggedIn()) {
        // Allow guest by using a system account (id=0 not valid FK); require login for tracking
        $errors[] = 'Please <a href="'.APP_URL.'/auth/login.php">log in</a> to send an emergency request (so donors can follow up).';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Insert emergency request
            $ins = $pdo->prepare('INSERT INTO emergency_requests (blood_group, location, city, latitude, longitude, patient_name, contact_number, notes, requested_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $ins->execute([$bloodGroup, $location, $city, $lat, $lng, $patientName, $contact, $notes, $requestedBy]);
            $requestId = (int) $pdo->lastInsertId();

            // Find matching donors
            $dq = $pdo->prepare("
                SELECT d.id, d.latitude, d.longitude, d.city
                FROM donors d
                WHERE d.blood_group = ?
                  AND d.availability_status = 'available_now'
            ");
            $dq->execute([$bloodGroup]);
            $matchingDonors = $dq->fetchAll();

            $notifiedCount = 0;
            foreach ($matchingDonors as $md) {
                // City match OR within radius (if GPS available)
                $matched = false;
                if ($lat && $lng && $md['latitude'] && $md['longitude']) {
                    $dist = haversineKm($lat, $lng, (float)$md['latitude'], (float)$md['longitude']);
                    if ($dist <= SOS_RADIUS_KM) $matched = true;
                }
                if (!$matched && strcasecmp(trim($md['city']), trim($city)) === 0) {
                    $matched = true;
                }
                if ($matched) {
                    $msg = "🚨 EMERGENCY: {$bloodGroup} blood needed at {$location}, {$city}. Patient: {$patientName}. Please respond ASAP.";
                    createNotification((int)$md['id'], $msg, $requestId);
                    $notifiedCount++;
                }
            }

            $pdo->commit();
            $success = true;
            $_SESSION['sos_result'] = [
                'request_id'     => $requestId,
                'blood_group'    => $bloodGroup,
                'notified_count' => $notifiedCount,
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to send SOS. Please try again.';
        }
    }
}

$sosResult = $_SESSION['sos_result'] ?? null;
unset($_SESSION['sos_result']);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in" style="text-align:center;">
  <h2 style="color:var(--red);">🚨 Emergency Blood Request</h2>
  <p>This will immediately notify all matching available donors nearby.</p>
</div>

<?php if ($success && $sosResult): ?>
  <div class="card fade-in" style="border-color:var(--red);text-align:center;max-width:500px;margin:0 auto 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">✅</div>
    <h3 style="font-family:var(--font-head);font-size:1.6rem;margin-bottom:.5rem;">SOS Sent!</h3>
    <p class="text-muted">
      <strong><?= (int)$sosResult['notified_count'] ?></strong> matching donor<?= $sosResult['notified_count'] !== 1 ? 's' : '' ?> have been alerted for
      <strong><?= sanitize($sosResult['blood_group']) ?></strong> blood.
    </p>
    <?php if ($sosResult['notified_count'] === 0): ?>
      <div class="alert alert-warning" style="margin-top:1rem;">No immediately available donors found. Try searching manually or expand to nearby cities.</div>
    <?php endif; ?>
    <div style="display:flex;gap:.75rem;justify-content:center;margin-top:1.5rem;">
      <a href="<?= APP_URL ?>/patient/search.php?blood_group=<?= urlencode($sosResult['blood_group']) ?>" class="btn btn-outline">Search All Donors</a>
      <a href="<?= APP_URL ?>/patient/emergency.php" class="btn btn-primary">Send Another</a>
    </div>
  </div>
<?php else: ?>

<div class="sos-panel fade-in" style="max-width:600px;margin:0 auto;">

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= $e /* contains link – intentionally not escaped */ ?></div>
  <?php endforeach; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

    <div class="form-group">
      <label>Blood Group Needed <span style="color:var(--red)">*</span></label>
      <select name="blood_group" class="form-control" required>
        <option value="">— Select Blood Group —</option>
        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
          <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Hospital / Location <span style="color:var(--red)">*</span></label>
        <input type="text" name="location" class="form-control" value="<?= sanitize($_POST['location'] ?? '') ?>" placeholder="e.g. City Hospital, MG Road" required>
      </div>
      <div class="form-group">
        <label>City <span style="color:var(--red)">*</span></label>
        <input type="text" name="city" class="form-control" value="<?= sanitize($_POST['city'] ?? '') ?>" placeholder="e.g. Goa" required>
      </div>
    </div>

    <div class="form-row" id="gps-row" style="display:none;">
      <div class="form-group">
        <label>Latitude</label>
        <input type="number" step="any" name="lat" id="lat-f" class="form-control" value="<?= sanitize($_POST['lat'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Longitude</label>
        <input type="number" step="any" name="lng" id="lng-f" class="form-control" value="<?= sanitize($_POST['lng'] ?? '') ?>">
      </div>
    </div>
    <button type="button" class="btn btn-outline btn-sm mb-2" onclick="getGPS()">📍 Auto GPS (for radius matching)</button>

    <div class="form-row">
      <div class="form-group">
        <label>Patient Name</label>
        <input type="text" name="patient_name" class="form-control" value="<?= sanitize($_POST['patient_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Contact Number <span style="color:var(--red)">*</span></label>
        <input type="tel" name="contact_number" class="form-control" value="<?= sanitize($_POST['contact_number'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label>Additional Notes</label>
      <textarea name="notes" class="form-control" rows="3" placeholder="Units needed, urgency level, etc."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-sos" style="width:100%;margin-top:.5rem;">🚨 Send Emergency SOS</button>
  </form>
</div>

<?php endif; ?>

<script>
function getGPS() {
  if (!navigator.geolocation) return alert('Not supported');
  navigator.geolocation.getCurrentPosition(p => {
    document.getElementById('lat-f').value = p.coords.latitude.toFixed(7);
    document.getElementById('lng-f').value = p.coords.longitude.toFixed(7);
    document.getElementById('gps-row').style.display = '';
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
