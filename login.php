<?php
require 'includes.php';

$error = '';
$fieldErrors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if ($email === '') {
        $fieldErrors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }
    if (($_POST['password'] ?? '') === '') {
        $fieldErrors['password'] = 'Please enter your password.';
    }

    if (!$fieldErrors) {
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
                $error = $attempts >= 3 ? 'Too many attempts. Try again later.' : 'Incorrect email or password.';
            }
        }
    }
}

head('Login');
?>

<form method="post" class="card narrow login-form" id="login-form" novalidate>
  <h2>Welcome back</h2>
  <p class="muted">Find and reserve parking across Sabah.</p>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <div class="login-field">
    <label for="login-email">Email address</label>
    <input id="login-email" type="email" name="email" placeholder="Enter your email address" autocomplete="username" value="<?= e($email) ?>" aria-describedby="login-email-error" aria-invalid="<?= isset($fieldErrors['email']) ? 'true' : 'false' ?>" required>
    <p class="field-feedback" id="login-email-error" aria-live="polite" <?= !isset($fieldErrors['email']) ? 'hidden' : '' ?>><span data-error-text><?= e($fieldErrors['email'] ?? '') ?></span></p>
  </div>
  <div class="password-field">
    <label for="login-password">Password</label>
    <div class="password-field__control">
      <input id="login-password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" aria-describedby="login-password-error" aria-invalid="<?= isset($fieldErrors['password']) ? 'true' : 'false' ?>" required>
    </div>
    <p class="field-feedback" id="login-password-error" aria-live="polite" <?= !isset($fieldErrors['password']) ? 'hidden' : '' ?>><span data-error-text><?= e($fieldErrors['password'] ?? '') ?></span></p>
  </div>
  <p class="login-forgot"><a href="forgot.php">Forgot password?</a></p>
  <p class="login-error" id="login-request-error" role="alert" tabindex="-1" <?= !$error ? 'hidden' : '' ?>><span data-request-error><?= e($error) ?></span></p>
  <button class="login-submit" type="submit"><span class="login-spinner" aria-hidden="true" hidden></span><span data-submit-label>Sign in</span></button>
  <span class="validation-sr-only" id="login-status" role="status" aria-live="polite"></span>
  <p class="login-signup">Don’t have an account? <a href="register.php">Sign up</a></p>
  <details class="login-demo"><summary>Demo accounts</summary><p class="hint">Superadmin: superadmin@park.com / super123<br>Admin: admin@park.com / admin123<br>Staff: staff@park.com / staff123<br>Member: user@park.com / user123</p></details>

</form>

<script src="assets/Js/login-validation.js" defer></script>
<?php foot(); ?>
