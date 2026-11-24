<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/password-reset.php';
require __DIR__ . '/includes/registration-smtp.php';

header('Cache-Control: private, no-store');
$pending = $_SESSION['password_reset_pending'] ?? null;
$showReset = ($_GET['step'] ?? '') === 'reset' && $pending !== null;
$email = $showReset ? $pending['email'] : '';
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'request';
    try {
        if ($action === 'request') {
            $showReset = false;
            $email = strtolower(trim($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.', 422);
            }
            password_reset_issue_code($email, 'send_password_reset_code');
            header('Location: forgot.php?step=reset', true, 303);
            exit;
        }
        if ($pending === null) throw new RuntimeException('Request a password reset code first.', 422);
        $showReset = true;
        $email = $pending['email'];
        if ($action === 'resend') {
            password_reset_issue_code($email, 'send_password_reset_code');
            header('Location: forgot.php?step=reset', true, 303);
            exit;
        }
        if ($action !== 'reset') throw new RuntimeException('Invalid request fields.', 400);
        $accountId = password_reset_complete(
            trim($_POST['code'] ?? ''),
            $_POST['password'] ?? '',
            $_POST['confirm'] ?? ''
        );
        if ((me()['id'] ?? null) === $accountId) unset($_SESSION['user'], $_SESSION['demo_last_active']);
        unset($_SESSION['csrf']);
        session_regenerate_id(true);
        flash('Password updated. Sign in with your new password.');
        header('Location: pages/login.php', true, 303);
        exit;
    } catch (RuntimeException $error) {
        http_response_code($error->getCode());
        $formError = $error->getMessage();
    }
}

head('Password recovery');
?>
<section class="card narrow email-code-card" aria-labelledby="password-reset-heading">
  <h2 id="password-reset-heading"><?= te('Reset your password') ?></h2>
  <?php if ($formError !== ''): ?><p class="registration-summary" role="alert"><?= te($formError) ?></p><?php endif; ?>
  <?php if ($showReset): ?>
    <p class="email-code-intro"><?= te('If an account uses {email}, a password reset code will be sent there. The code expires in 10 minutes.', ['email' => $email]) ?></p>
    <form method="post" action="forgot.php?step=reset">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="action" value="reset">
      <div class="email-code-field">
        <label for="reset-code"><?= te('Verification code') ?></label>
        <input class="email-code-input" id="reset-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
      </div>
      <div class="email-code-field">
        <label for="reset-password"><?= te('New password') ?></label>
        <div class="password-field__control">
          <input id="reset-password" name="password" type="password" autocomplete="new-password" minlength="8" required>
        </div>
      </div>
      <div class="email-code-field">
        <label for="reset-password-confirm"><?= te('Confirm password') ?></label>
        <div class="password-field__control">
          <input id="reset-password-confirm" name="confirm" type="password" autocomplete="new-password" minlength="8" required>
        </div>
      </div>
      <button type="submit" class="email-code-submit"><?= te('Reset password') ?></button>
    </form>
    <form method="post" action="forgot.php?step=reset" class="registration-code-actions">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <button type="submit" name="action" value="resend" class="ghost"><?= te('Resend code') ?></button>
      <a href="forgot.php"><?= te('Change email address') ?></a>
    </form>
  <?php else: ?>
    <p class="email-code-intro"><?= te('Enter your email address to request a password reset code.') ?></p>
    <form method="post" action="forgot.php">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="action" value="request">
      <div class="email-code-field">
        <label for="reset-email"><?= te('Email address') ?></label>
        <input id="reset-email" type="email" name="email" autocomplete="email" value="<?= e($email) ?>" required>
      </div>
      <button type="submit" class="email-code-submit"><?= te('Send reset code') ?></button>
    </form>
  <?php endif; ?>
  <p class="email-code-back"><a href="pages/login.php"><?= te('Back to sign in') ?></a></p>
</section>
<?php foot(false); ?>
