<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isLoggedIn()) { header('Location: ' . APP_URL); exit; }

$errors = [];
$post   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $post['name']     = trim($_POST['name']     ?? '');
    $post['email']    = trim($_POST['email']    ?? '');
    $post['phone']    = trim($_POST['phone']    ?? '');
    $post['role']     = trim($_POST['role']     ?? 'donor');
    $post['password'] = $_POST['password']      ?? '';
    $post['confirm']  = $_POST['confirm']       ?? '';

    // Donor fields
    $post['blood_group'] = $_POST['blood_group'] ?? '';
    $post['city']        = trim($_POST['city']   ?? '');
    $post['lat']         = $_POST['lat']          ?? '';
    $post['lng']         = $_POST['lng']          ?? '';

    // Validate
    if (strlen($post['name']) < 2)              $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($post['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($post['password']) < 6)          $errors[] = 'Password must be at least 6 characters.';
    if ($post['password'] !== $post['confirm']) $errors[] = 'Passwords do not match.';
    if (!in_array($post['role'], ['donor','patient','hospital'])) $errors[] = 'Invalid role.';

    $bloodGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    if ($post['role'] === 'donor') {
        if (!in_array($post['blood_group'], $bloodGroups)) $errors[] = 'Select a valid blood group.';
        if (empty($post['city'])) $errors[] = 'City is required for donors.';
    }

    if (empty($errors)) {
        $pdo = getDB();
        // Check duplicate email
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$post['email']]);
        if ($chk->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            $hash = password_hash($post['password'], PASSWORD_BCRYPT);
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare('INSERT INTO users (name, email, password, role, phone) VALUES (?,?,?,?,?)');
                $ins->execute([$post['name'], $post['email'], $hash, $post['role'], $post['phone']]);
                $userId = (int) $pdo->lastInsertId();

                if ($post['role'] === 'donor') {
                    $lat = is_numeric($post['lat']) ? (float)$post['lat'] : null;
                    $lng = is_numeric($post['lng']) ? (float)$post['lng'] : null;
                    $ins2 = $pdo->prepare('INSERT INTO donors (user_id, blood_group, city, latitude, longitude) VALUES (?,?,?,?,?)');
                    $ins2->execute([$userId, $post['blood_group'], $post['city'], $lat, $lng]);
                } elseif ($post['role'] === 'hospital') {
                    $ins3 = $pdo->prepare('INSERT INTO hospitals (user_id, city, address) VALUES (?,?,?)');
                    $ins3->execute([$userId, $post['city'] ?? '', '']);
                }

                $pdo->commit();

                $_SESSION['user_id']   = $userId;
                $_SESSION['user_name'] = $post['name'];
                $_SESSION['role']      = $post['role'];
                session_regenerate_id(true);

                setFlash('success', 'Account created! Welcome to LifeLink, ' . $post['name'] . '.');
                if ($post['role'] === 'donor')    header('Location: ' . APP_URL . '/donor/dashboard.php');
                elseif ($post['role'] === 'hospital') header('Location: ' . APP_URL . '/hospital/dashboard.php');
                else header('Location: ' . APP_URL . '/patient/search.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="auth-wrap" style="align-items:flex-start;padding-top:3rem;">
  <div class="auth-card fade-in" style="max-width:520px;">
    <h2>Create account</h2>
    <p class="sub">Join LifeLink and save lives</p>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= sanitize($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="" id="reg-form">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

      <div class="form-group">
        <label>I am registering as</label>
        <select name="role" id="role-select" class="form-control" onchange="toggleDonorFields()">
          <option value="donor"    <?= ($post['role'] ?? '') === 'donor'    ? 'selected' : '' ?>>Blood Donor</option>
          <option value="patient"  <?= ($post['role'] ?? '') === 'patient'  ? 'selected' : '' ?>>Patient / Family</option>
          <option value="hospital" <?= ($post['role'] ?? '') === 'hospital' ? 'selected' : '' ?>>Hospital / Blood Bank</option>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" class="form-control" value="<?= sanitize($post['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" name="phone" class="form-control" value="<?= sanitize($post['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control" value="<?= sanitize($post['email'] ?? '') ?>" required>
      </div>

      <!-- Donor-specific fields -->
      <div id="donor-fields">
        <div class="form-row">
          <div class="form-group">
            <label>Blood Group</label>
            <select name="blood_group" class="form-control">
              <option value="">Select group</option>
              <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($post['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>City</label>
            <input type="text" name="city" class="form-control" value="<?= sanitize($post['city'] ?? '') ?>" placeholder="e.g. Goa">
          </div>
        </div>
        <div class="form-row" style="display:none;" id="gps-row">
          <div class="form-group">
            <label>Latitude (optional)</label>
            <input type="number" step="any" name="lat" id="lat-field" class="form-control" value="<?= sanitize($post['lat'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Longitude (optional)</label>
            <input type="number" step="any" name="lng" id="lng-field" class="form-control" value="<?= sanitize($post['lng'] ?? '') ?>">
          </div>
        </div>
        <button type="button" class="btn btn-outline btn-sm mb-2" onclick="getGPS()">📍 Auto-detect location</button>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm" class="form-control" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem;">Create Account</button>
    </form>

    <hr class="divider">
    <p class="text-muted" style="text-align:center;font-size:.875rem;">
      Already have an account? <a href="<?= APP_URL ?>/auth/login.php">Sign in</a>
    </p>
  </div>
</div>

<script>
function toggleDonorFields() {
  const role = document.getElementById('role-select').value;
  document.getElementById('donor-fields').style.display = role === 'donor' ? '' : 'none';
}
function getGPS() {
  if (!navigator.geolocation) { alert('Geolocation not supported'); return; }
  navigator.geolocation.getCurrentPosition(pos => {
    document.getElementById('lat-field').value = pos.coords.latitude.toFixed(7);
    document.getElementById('lng-field').value = pos.coords.longitude.toFixed(7);
    document.getElementById('gps-row').style.display = '';
  }, () => alert('Could not detect location'));
}
toggleDonorFields();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
