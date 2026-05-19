<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
requireRole('hospital');

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];
$errors = [];

// Fetch all available donors for dropdown
$dStmt = $pdo->prepare("SELECT d.id, u.name, d.blood_group, d.city FROM donors d JOIN users u ON u.id = d.user_id ORDER BY u.name ASC");
$dStmt->execute();
$allDonors = $dStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $donorId = (int)($_POST['donor_id'] ?? 0);
    $date    = $_POST['date'] ?? '';
    $notes   = trim($_POST['notes'] ?? '');

    if (!$donorId) $errors[] = 'Please select a donor.';
    if (!$date)    $errors[] = 'Date is required.';

    if (empty($errors)) {
        // Insert donation record (pending – hospital confirms later)
        $ins = $pdo->prepare('INSERT INTO donations (donor_id, hospital_id, date, status, notes) VALUES (?,?,?,?,?)');
        $ins->execute([$donorId, $userId, $date, 'pending', $notes]);
        setFlash('success', 'Donation recorded. Confirm once completed.');
        header('Location: ' . APP_URL . '/hospital/dashboard.php'); exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
  <h2>Record Donation</h2>
  <p>Log a donation event. Confirm after the donor completes donation to update their score.</p>
</div>

<div class="card fade-in" style="max-width:520px;">
  <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

    <div class="form-group">
      <label>Select Donor</label>
      <select name="donor_id" class="form-control" required>
        <option value="">— Choose donor —</option>
        <?php foreach ($allDonors as $d): ?>
          <option value="<?= $d['id'] ?>" <?= ((int)($_POST['donor_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>
            <?= sanitize($d['name']) ?> — <?= sanitize($d['blood_group']) ?> (<?= sanitize($d['city']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Donation Date</label>
      <input type="date" name="date" class="form-control" value="<?= sanitize($_POST['date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="form-group">
      <label>Notes (optional)</label>
      <textarea name="notes" class="form-control" rows="3" placeholder="Units, blood type confirmed, etc."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:.75rem;margin-top:1rem;">
      <button type="submit" class="btn btn-primary">Record Donation</button>
      <a href="<?= APP_URL ?>/hospital/dashboard.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
