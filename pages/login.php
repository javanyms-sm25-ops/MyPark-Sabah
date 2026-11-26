<?php
const MYPARK_AUTH_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/login-recaptcha.php';

$recaptchaPath = dirname(__DIR__) . '/config/recaptcha.local.php';
$recaptchaConfig = is_file($recaptchaPath) ? require $recaptchaPath : [];
$recaptchaSiteKey = trim((string)($recaptchaConfig['site_key'] ?? ''));

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
        $error = login_recaptcha_error($recaptchaConfig, $_POST['g-recaptcha-response'] ?? '');
    }

    if (!$fieldErrors && $error === '') {
        $user = null;
        $userIndex = null;

        foreach (MyParkData::$records['demo_users'] as $index => $candidate) {
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
            unset($_SESSION['csrf']);
            $_SESSION['demo_last_active'] = time();
            $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
            foreach (['phone', 'photo'] as $key) if (isset($user[$key])) $_SESSION['user'][$key] = $user[$key];
            header('Location: ' . ($user['role'] === 'staff' ? '../staff.php' : ($user['role'] === 'user' ? '../dashboard.php' : '../admin/dashboard.php')));
            exit;
        } else {
            $attempts = ($_SESSION['failures'][$email] ?? 0) + 1;
            $_SESSION['failures'][$email] = $attempts;

            if ($attempts >= 3 && $user) {
                MyParkData::$records['demo_users'][$userIndex]['blocked'] = true;
                $error = 'Account blocked after 3 failed attempts. Ask an administrator to unblock it.';
            } else {
                $error = $attempts >= 3 ? 'Too many attempts. Try again later.' : 'Incorrect email or password.';
            }
        }
    }
}

head('Login');
?>

<form method="post" action="pages/login.php" class="card narrow login-form" id="login-form" data-recaptcha-site-key="<?= e($recaptchaSiteKey) ?>" novalidate>
  <h2><?= te('Welcome back') ?></h2>
  <p class="muted"><?= te('Find parking across Sabah.') ?></p>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <div class="login-field">
    <label for="login-email"><?= te('Email address') ?></label>
    <input id="login-email" type="email" name="email" placeholder="<?= te('Enter your email address') ?>" autocomplete="username" value="<?= e($email) ?>" aria-describedby="login-email-error" aria-invalid="<?= isset($fieldErrors['email']) ? 'true' : 'false' ?>" required>
    <p class="field-feedback" id="login-email-error" aria-live="polite" <?= !isset($fieldErrors['email']) ? 'hidden' : '' ?>><span data-error-text><?= te($fieldErrors['email'] ?? '') ?></span></p>
  </div>
  <div class="password-field">
    <label for="login-password"><?= te('Password') ?></label>
    <div class="password-field__control">
      <input id="login-password" type="password" name="password" placeholder="<?= te('Enter your password') ?>" autocomplete="current-password" aria-describedby="login-password-error" aria-invalid="<?= isset($fieldErrors['password']) ? 'true' : 'false' ?>" required>
    </div>
    <p class="field-feedback" id="login-password-error" aria-live="polite" <?= !isset($fieldErrors['password']) ? 'hidden' : '' ?>><span data-error-text><?= te($fieldErrors['password'] ?? '') ?></span></p>
  </div>
  <p class="login-forgot"><a href="forgot.php"><?= te('Forgot password?') ?></a></p>
  <p class="login-error" id="login-request-error" role="alert" tabindex="-1" <?= !$error ? 'hidden' : '' ?>><span data-request-error><?= te($error) ?></span></p>
  <button class="login-submit" type="submit"><span class="login-spinner" aria-hidden="true" hidden></span><span data-submit-label><?= te('Sign in') ?></span></button>
  <span class="validation-sr-only" id="login-status" role="status" aria-live="polite"></span>
  <p class="login-signup"><?= te('Don’t have an account?') ?> <a href="pages/register.php"><?= te('Sign up') ?></a></p>
  <details class="login-demo"><summary><?= te('Demo accounts') ?></summary><p class="hint">Superadmin: superadmin@park.com / super123<br>Admin: admin@park.com / admin123<br>Staff: staff@park.com / staff123<br>Member: user@park.com / user123</p></details>

</form>

<?php if ($recaptchaSiteKey !== ''): ?>
<script id="login-recaptcha-script" src="https://www.google.com/recaptcha/api.js?render=<?= rawurlencode($recaptchaSiteKey) ?>" async></script>
<?php endif; ?>
<script src="assets/js/login-validation.js" defer></script>
<?php foot(showChatbot: false); ?>
