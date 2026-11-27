<?php
const MYPARK_AUTH_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/account-password.php';

header('Cache-Control: private, no-store');
require_login('login.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        change_account_password($_POST['current_password'] ?? '', $_POST['password'] ?? '', $_POST['confirm'] ?? '');
        flash(['message' => 'Password updated.', 'type' => 'success']);
        header('Location: ../profile.php', true, 303);
        exit;
    } catch (RuntimeException $failure) {
        http_response_code($failure->getCode());
        $error = $failure->getMessage();
    }
}

head('Change password');
?>
<section class="card narrow email-code-card" aria-labelledby="change-password-heading">
  <h2 id="change-password-heading"><?= te('Change password') ?></h2>
  <?php if ($error !== ''): ?><p class="registration-summary" role="alert"><?= te($error) ?></p><?php endif; ?>
  <form method="post" action="pages/change-password.php">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <div class="email-code-field">
      <label for="change-current-password"><?= te('Current password') ?></label>
      <div class="password-field__control">
        <input id="change-current-password" name="current_password" type="password" autocomplete="current-password" required>
      </div>
    </div>
    <div class="email-code-field">
      <label for="change-new-password"><?= te('New password') ?></label>
      <div class="password-field__control">
        <input id="change-new-password" name="password" type="password" autocomplete="new-password"
               minlength="8" aria-describedby="change-password-hint" required>
      </div>
      <p class="hint" id="change-password-hint"><?= te('At least 8 characters') ?></p>
    </div>
    <div class="email-code-field">
      <label for="change-confirm-password"><?= te('Confirm password') ?></label>
      <div class="password-field__control">
        <input id="change-confirm-password" name="confirm" type="password" autocomplete="new-password" minlength="8" required>
      </div>
    </div>
    <button type="submit" class="email-code-submit"><?= te('Change password') ?></button>
  </form>
  <p class="email-code-back"><a href="profile.php"><?= te('Back to profile') ?></a></p>
</section>
<?php foot(showChatbot: false); ?>
