<?php
require 'includes.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $user = null;
    $userIndex = null;

    foreach ($_SESSION['demo_users'] as $index => $candidate) {
        if ($candidate['email'] === $email) {
            $user = $candidate;
            $userIndex = $index;
            break;
        }
    }

    if ($user && $user['blocked']) {
        $error = 'This account is blocked.';
    } elseif ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        $_SESSION['failures'][$email] = 0;
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
        header('Location: ' . ($user['role'] === 'user' ? 'dashboard.php' : 'admin.php'));
        exit;
    } else {
        $attempts = ($_SESSION['failures'][$email] ?? 0) + 1;
        $_SESSION['failures'][$email] = $attempts;

        if ($attempts >= 3 && $user) {
            $_SESSION['demo_users'][$userIndex]['blocked'] = true;
            $error = 'Account blocked after 3 failed attempts. Ask an administrator to unblock it.';
        } else {
            $error = $attempts >= 3 ? 'Too many attempts. Try again later.' : 'Invalid email or password.';
        }
    }
}

head('Login');
?>

<form method="post" class="card narrow">
  <h2>Welcome back</h2>
  <p class="muted">Find and reserve parking across Sabah.</p>
  <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <label>Email<input type="email" name="email" required></label>
  <div class="password-field">
    <label for="login-password">Password</label>
    <div class="password-field__control">
      <input id="login-password" type="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
    </div>
  </div>
  <button>Sign in</button>
  <p class="hint">Demo accounts:<br>Superadmin: superadmin@park.com / super123<br>Admin: admin@park.com / admin123<br>Staff: staff@park.com / staff123<br>Member: user@park.com / user123</p>
  <p><a href="forgot.php">Forgot password?</a></p>
</form>

<?php foot(); ?>
