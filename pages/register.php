<?php
const MYPARK_AUTH_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/registration-verification.php';
require dirname(__DIR__) . '/includes/registration-smtp.php';

header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['verify'])) {
    $token = $_GET['verify'];
    unset($_SESSION['registration_confirmation']);
    try {
        if (!is_string($token) || !preg_match('/\A[0-9a-f]{64}\z/', $token)) {
            throw new RuntimeException(REGISTRATION_LINK_INVALID, 422);
        }
        $tokenHash = hash('sha256', $token);
        registration_confirmation_request($tokenHash);
        $_SESSION['registration_confirmation'] = $tokenHash;
        $destination = 'register.php?step=confirm';
    } catch (RuntimeException $error) {
        $_SESSION['registration_link_error'] = $error->getMessage();
        $destination = isset($_SESSION['registration_pending']) ? 'register.php?step=verify' : 'register.php';
    }
    header('Location: ' . $destination, true, 303);
    exit;
}

$errors = [];
$formError = $_SESSION['registration_link_error'] ?? '';
unset($_SESSION['registration_link_error']);
$pending = $_SESSION['registration_pending'] ?? null;
$verifyScreen = ($_GET['step'] ?? '') === 'verify' && $pending !== null;
$confirmScreen = ($_GET['step'] ?? '') === 'confirm';
$confirmation = null;
$action = $_POST['action'] ?? 'register';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
}
if ($confirmScreen || ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'confirm')) {
    $tokenHash = $_SESSION['registration_confirmation'] ?? '';
    try {
        $confirmation = registration_confirmation_request($tokenHash);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'confirm') {
            if (!hash_equals($tokenHash, $_POST['confirmation'] ?? '')) {
                throw new RuntimeException('Open your verification link again to confirm this registration.', 422);
            }
            registration_complete($tokenHash, $_POST['registration_password'] ?? '');
            flash('Account created. You can now sign in.');
            header('Location: login.php', true, 303);
            exit;
        }
        $confirmationNeedsPassword = !registration_confirmation_is_local($tokenHash);
    } catch (RuntimeException $error) {
        $formError = $error->getMessage();
        http_response_code($error->getCode());
        if ($confirmation === null || $error->getCode() === 409) {
            unset($_SESSION['registration_confirmation']);
            $confirmation = null;
            $pending = $_SESSION['registration_pending'] ?? null;
            $verifyScreen = $pending !== null;
        } else {
            $confirmationNeedsPassword = !registration_confirmation_is_local($tokenHash);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    $verifyScreen = false;
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors['name'] = 'Please enter your name.';
    elseif (preg_match_all('/./us', $name) < 2) $errors['name'] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter an email address like name@example.com.';
    if (preg_match_all('/./us', $password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
    elseif (strlen($password) > 72) $errors['password'] = 'Password must be no more than 72 bytes (some characters use more than one byte).';
    if (($_POST['confirm'] ?? '') === '') $errors['confirm'] = 'Please confirm your password.';
    elseif ($password !== ($_POST['confirm'] ?? '')) $errors['confirm'] = 'Passwords do not match.';

    if (registration_email_exists($email)) $errors['email'] = 'Email already registered.';

    if (!$errors) {
        try {
            $account = [
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ];
            registration_issue_link($account, 'send_registration_link');
            header('Location: register.php?step=verify', true, 303);
            exit;
        } catch (RuntimeException $error) {
            http_response_code($error->getCode());
            $formError = $error->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action !== 'resend') throw new RuntimeException('Invalid request fields.', 400);
        if ($pending === null) throw new RuntimeException('Start registration to request a verification link.', 422);
        if (registration_email_exists($pending['account']['email'])) {
            unset($_SESSION['registration_pending']);
            throw new RuntimeException('Email already registered.', 409);
        }
        $verifyScreen = true;
        $resendWait = registration_resend_wait($pending);
        if ($resendWait > 0) {
            header('Retry-After: ' . $resendWait);
            http_response_code(429);
        } else {
            registration_issue_link($pending['account'], 'send_registration_link');
            flash('A new verification link has been sent.');
            header('Location: register.php?step=verify', true, 303);
            exit;
        }
    } catch (RuntimeException $error) {
        http_response_code($error->getCode());
        $formError = $error->getMessage();
        $pending = $_SESSION['registration_pending'] ?? null;
        $verifyScreen = $pending !== null;
    }
}

head($confirmation !== null || $verifyScreen ? 'Verify your email' : 'Register');
?>

<?php if ($confirmation !== null): ?>
  <section class="card narrow registration-form email-code-card" aria-labelledby="verify-email-heading">
    <h2 id="verify-email-heading"><?= te('Verify your email') ?></h2>
    <p class="email-code-intro"><?= te('Confirm your email address to create your account for {email}.',
        ['email' => $confirmation['account']['email']]) ?></p>
    <?php if ($formError !== ''): ?><p class="registration-summary" role="alert"><?= te($formError) ?></p><?php endif; ?>
    <form method="post" action="pages/register.php?step=confirm">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="action" value="confirm">
      <input type="hidden" name="confirmation" value="<?= e($tokenHash) ?>">
      <?php if ($confirmationNeedsPassword): ?>
        <div class="email-code-field">
          <label for="verification-password"><?= te('Password') ?></label>
          <div class="password-field__control">
            <input id="verification-password" name="registration_password" type="password" autocomplete="current-password"
                   aria-describedby="verification-password-hint" required>
          </div>
          <p class="hint" id="verification-password-hint"><?= te('Enter the password you chose when you registered.') ?></p>
        </div>
      <?php endif; ?>
      <button class="email-code-submit" type="submit"><?= te('Confirm registration') ?></button>
    </form>
  </section>
<?php elseif ($verifyScreen): $resendWait = registration_resend_wait($pending); ?>
  <section class="card narrow registration-form email-code-card" aria-labelledby="verify-email-heading">
    <h2 id="verify-email-heading"><?= te('Verify your email') ?></h2>
    <?php if (registration_link_is_active($pending)): ?>
      <p class="email-code-intro"><?= te('Open the verification link sent to {email}. The link expires in 10 minutes.', ['email' => $pending['account']['email']]) ?></p>
    <?php else: ?>
      <p class="email-code-intro"><?= te('Request a new verification link to finish registering.') ?></p>
    <?php endif; ?>
    <?php if ($formError !== ''): ?><p class="registration-summary" role="alert"><?= te($formError) ?></p><?php endif; ?>
    <form id="registration-resend" method="post" action="pages/register.php?step=verify" class="registration-code-actions">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="resend">
      <button type="submit" class="ghost" aria-describedby="registration-resend-status"><?= te('Resend verification link') ?></button>
      <a class="btn btn-secondary" href="pages/register.php"><?= te('Change email address') ?></a>
    </form>
    <p class="email-code-back" id="registration-resend-status" data-resend-seconds="<?= $resendWait ?>" <?= $resendWait === 0 ? 'hidden' : '' ?>>
      <?= te('You can resend the link in {seconds}s.', ['seconds' => $resendWait]) ?>
    </p>
  </section>
<?php else: ?>
<form method="post" action="pages/register.php" class="card narrow registration-form" id="registration-form" novalidate>
  <h2><?= te('Create member account') ?></h2>
  <?php if ($formError !== ''): ?><p class="registration-summary" role="alert"><?= te($formError) ?></p><?php endif; ?>
  <p class="registration-summary" id="registration-summary" role="alert" tabindex="-1" <?= !$errors ? 'hidden' : '' ?>><?= te('Please check the highlighted fields below.') ?></p>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <div class="registration-field">
    <label for="register-name"><?= te('Full name') ?></label>
    <input id="register-name" name="name" placeholder="<?= te('Enter your full name') ?>" autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>" aria-describedby="register-name-error" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>" required>
    <p class="field-feedback" id="register-name-error" <?= !isset($errors['name']) ? 'hidden' : '' ?>><span data-error-text><?= te($errors['name'] ?? '') ?></span></p>
  </div>
  <div class="registration-field">
    <label for="register-email"><?= te('Email') ?></label>
    <input id="register-email" name="email" type="email" placeholder="<?= te('Enter your email address') ?>" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>" aria-describedby="register-email-error" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>" data-email-taken="<?= ($errors['email'] ?? '') === 'Email already registered.' ? 'true' : 'false' ?>" required>
    <p class="field-feedback" id="register-email-error" <?= !isset($errors['email']) ? 'hidden' : '' ?>><span data-error-text><?= te($errors['email'] ?? '') ?></span></p>
  </div>
  <div class="password-field">
    <label for="register-password"><?= te('Password') ?></label>
    <div class="password-field__control">
      <input id="register-password" type="password" name="password" minlength="8" placeholder="<?= te('Enter password') ?>" autocomplete="new-password" aria-describedby="register-password-requirements register-password-error" aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>" required>
    </div>
    <ul class="password-requirements" id="register-password-requirements">
      <li id="password-length-rule"><span><?= te('At least 8 characters') ?></span><span class="validation-sr-only" id="password-rule-status" role="status"> — not met</span></li>
    </ul>
    <p class="field-feedback" id="register-password-error" <?= !isset($errors['password']) ? 'hidden' : '' ?>><span data-error-text><?= te($errors['password'] ?? '') ?></span></p>
  </div>
  <div class="password-field">
    <label for="register-confirm-password"><?= te('Confirm password') ?></label>
    <div class="password-field__control">
      <input id="register-confirm-password" type="password" name="confirm" placeholder="<?= te('Enter password') ?>" autocomplete="new-password" aria-describedby="register-confirm-error register-password-match" aria-invalid="<?= isset($errors['confirm']) ? 'true' : 'false' ?>" required>
    </div>
    <p class="field-feedback" id="register-confirm-error" <?= !isset($errors['confirm']) ? 'hidden' : '' ?>><span data-error-text><?= te($errors['confirm'] ?? '') ?></span></p>
    <p class="password-match" id="register-password-match" role="status"></p>
  </div>
  <button type="submit"><?= te('Send verification link') ?></button>
</form>

<?php endif; ?>
<script src="assets/js/register-validation.js" defer></script>
<?php foot(showChatbot: false); ?>
