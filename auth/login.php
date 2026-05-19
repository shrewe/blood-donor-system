<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isLoggedIn()) { header('Location: ' . APP_URL); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];
            session_regenerate_id(true);

            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            if ($user['role'] === 'donor')    header('Location: ' . APP_URL . '/donor/dashboard.php');
            elseif ($user['role'] === 'hospital') header('Location: ' . APP_URL . '/hospital/dashboard.php');
            else header('Location: ' . APP_URL . '/patient/search.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card fade-in">
    <h2>Welcome back</h2>
    <p class="sub">Sign in to your LifeLink account</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

      <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= sanitize($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem;">Sign In</button>
    </form>

    <hr class="divider">
    <p class="text-muted" style="text-align:center;font-size:.875rem;">
      No account? <a href="<?= APP_URL ?>/auth/register.php">Register here</a>
    </p>
    <p class="text-muted" style="text-align:center;font-size:.8rem;margin-top:.5rem;">
      Demo: <code>rahul@example.com</code> / <code>password</code>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
