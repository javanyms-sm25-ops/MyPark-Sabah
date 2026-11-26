<?php
const MYPARK_AUTH_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/email-change.php';
require dirname(__DIR__) . '/includes/registration-smtp.php';

header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
require_login('login.php');

foreach (MyParkData::$records['demo_users'] as $index => $candidate) {
    if ($candidate['id'] === me()['id']) {
        $account =& MyParkData::$records['demo_users'][$index];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['verify'])) {
    try {
        email_change_verify_link($account, is_string($_GET['verify']) ? $_GET['verify'] : '');
    } catch (RuntimeException $error) {
        $_SESSION['email_change_error'] = $error->getMessage();
    }
    header('Location: change-email.php', true, 303);
    exit;
}

$message = $_SESSION['email_change_error'] ?? '';
unset($_SESSION['email_change_error']);
$pending = email_change_pending($account);
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'request':
                if ($pending !== null) throw new RuntimeException('Cancel the current request before starting a new one.', 409);
                $email = strtolower(trim($_POST['email'] ?? ''));
                email_change_check_password($account, $_POST['password'] ?? '');
                email_change_issue_link($account, $email, 'send_email_change_link');
                break;
            case 'resend':
                if (!isset($pending['token_hash'])) throw new RuntimeException(EMAIL_CHANGE_INVALID_LINK, 422);
                email_change_issue_link($account, $pending['new_email'], 'send_email_change_link');
                flash('A new verification link has been sent.');
                break;
            case 'confirm':
                $email = $pending['new_email'] ?? '';
                email_change_complete($account);
                flash('Email address updated.');
                header('Location: ../profile.php', true, 303);
                exit;
            case 'cancel':
                unset($_SESSION['email_change_pending']);
                header('Location: ../profile.php', true, 303);
                exit;
            default:
                throw new RuntimeException('Invalid request fields.', 400);
        }
        header('Location: change-email.php', true, 303);
        exit;
    } catch (RuntimeException $error) {
        http_response_code($error->getCode());
        $message = $error->getMessage();
        $pending = email_change_pending($account);
    }
}

head('Change email address');
?>
<section class="card narrow email-code-card" aria-labelledby="change-email-heading">
  <h2 id="change-email-heading"><?= te('Change email address') ?></h2>
  <p class="email-code-intro"><?= te('Current email address') ?><br><strong><?= e($account['email']) ?></strong></p>
  <?php if ($message !== ''): ?><p class="registration-summary" role="alert"><?= te($message) ?></p><?php endif; ?>
  <form method="post" action="pages/change-email.php">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <?php if ($pending === null): ?>
      <div class="email-code-field">
        <label for="new-email"><?= te('New email address') ?></label>
        <input id="new-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= e($email) ?>" required>
      </div>
      <div class="email-code-field password-field">
        <label for="change-email-password"><?= te('Current password') ?></label>
        <div class="password-field__control">
          <input id="change-email-password" name="password" type="password" autocomplete="current-password" required>
        </div>
      </div>
      <button class="email-code-submit" type="submit" name="action" value="request"><?= te('Send verification link') ?></button>
    <?php elseif (!empty($pending['verified'])): ?>
      <p class="email-code-intro"><?= te('You verified {email}. Confirm to use it to sign in.', ['email' => $pending['new_email']]) ?></p>
      <div class="row">
        <button type="submit" name="action" value="confirm"><?= te('Confirm email change') ?></button>
        <button class="btn btn-secondary" type="submit" name="action" value="cancel"><?= te('Cancel request') ?></button>
      </div>
    <?php else: ?>
      <p class="email-code-intro"><?= te('Open the verification link sent to {email} in this browser. The link expires in 10 minutes.', ['email' => $pending['new_email']]) ?></p>
      <div class="row">
        <button type="submit" name="action" value="resend"><?= te('Resend verification link') ?></button>
        <button class="btn btn-secondary" type="submit" name="action" value="cancel"><?= te('Cancel request') ?></button>
      </div>
    <?php endif; ?>
  </form>
  <p class="email-code-back"><a href="profile.php"><?= te('Back to profile') ?></a></p>
</section>
<?php foot(showChatbot: false); ?>
