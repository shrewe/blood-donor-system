<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
requireRole('donor');

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

$dStmt = $pdo->prepare('SELECT id FROM donors WHERE user_id = ?');
$dStmt->execute([$userId]);
$donorRow = $dStmt->fetch();
if (!$donorRow) { header('Location: ' . APP_URL); exit; }
$donorId = (int) $donorRow['id'];

// Mark all as read on page load
$pdo->prepare('UPDATE notifications SET status="read" WHERE donor_id = ? AND status = "unread"')->execute([$donorId]);

// Fetch all notifications
$nStmt = $pdo->prepare('
    SELECT n.*, er.blood_group, er.location, er.contact_number,
           u.name AS requester_name, u.phone AS requester_phone
    FROM notifications n
    LEFT JOIN emergency_requests er ON er.id = n.request_id
    LEFT JOIN users u ON u.id = er.requested_by
    WHERE n.donor_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
');
$nStmt->execute([$donorId]);
$notifications = $nStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
  <h2>Emergency Alerts</h2>
  <p>SOS notifications sent to you based on your blood group and location.</p>
</div>

<div class="card fade-in">
  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <div class="empty-icon">🔔</div>
      <p>No notifications yet. You'll receive alerts when emergency blood requests match your profile.</p>
    </div>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
    <div class="notif-item <?= $n['status'] === 'unread' ? 'unread' : '' ?>">
      <div style="flex:1">
        <div class="notif-msg">
          <?php if ($n['blood_group']): ?>
            <span class="blood-pill" style="width:36px;height:36px;font-size:.8rem;display:inline-flex;margin-right:.5rem;"><?= sanitize($n['blood_group']) ?></span>
          <?php endif; ?>
          <?= sanitize($n['message']) ?>
        </div>
        <?php if ($n['status'] === 'accepted' && ($n['contact_number'] || $n['requester_phone'])): ?>
          <div class="mt-1" style="font-size:.88rem;">
            📞 Contact: <strong><?= sanitize($n['contact_number'] ?: $n['requester_phone'] ?: '—') ?></strong>
            &nbsp;|&nbsp; Requested by: <?= sanitize($n['requester_name'] ?? 'Unknown') ?>
          </div>
        <?php endif; ?>
        <?php if (in_array($n['status'], ['unread','read']) && $n['request_id']): ?>
          <div class="notif-actions">
            <button class="btn btn-primary btn-sm notif-accept" data-id="<?= $n['id'] ?>" data-request-id="<?= $n['request_id'] ?>">✅ Accept</button>
            <button class="btn btn-outline btn-sm notif-reject" data-id="<?= $n['id'] ?>" data-request-id="<?= $n['request_id'] ?>">❌ Decline</button>
          </div>
        <?php elseif ($n['status'] === 'accepted'): ?>
          <div class="mt-1"><span class="status-badge status-available_now">Accepted</span></div>
        <?php elseif ($n['status'] === 'rejected'): ?>
          <div class="mt-1"><span class="status-badge status-not_available">Declined</span></div>
        <?php endif; ?>
      </div>
      <div class="notif-time"><?= date('d M, H:i', strtotime($n['created_at'])) ?></div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<input type="hidden" name="csrf_token" value="<?= csrf() ?>">

<?php include __DIR__ . '/../includes/footer.php'; ?>
