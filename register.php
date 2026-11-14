<?php
require 'includes.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors['name'] = 'Please enter your name.';
    elseif (preg_match_all('/./us', $name) < 2) $errors['name'] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter an email address like name@example.com.';
    if (preg_match_all('/./us', $password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
    if (($_POST['confirm'] ?? '') === '') $errors['confirm'] = 'Please confirm your password.';
    elseif ($password !== ($_POST['confirm'] ?? '')) $errors['confirm'] = 'Passwords do not match.';

    foreach ($_SESSION['demo_users'] as $user) {
        if ($user['email'] === $email) $errors['email'] = 'Email already registered.';
    }

    if (!$errors) {
        $_SESSION['demo_users'][] = [
            'id' => count($_SESSION['demo_users']) + 1,
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',
            'blocked' => false
        ];
        flash('Demo account created. Email OTP delivery will be connected with backend services later.');
        header('Location: login.php');
        exit;
    }
}

head('Register');
?>

<form method="post" class="card narrow registration-form" id="registration-form" novalidate>
  <h2>Create member account</h2>
  <p class="registration-summary" id="registration-summary" role="alert" tabindex="-1" <?= !$errors ? 'hidden' : '' ?>>Please check the highlighted fields below.</p>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <div class="registration-field">
    <label for="register-name">Full name</label>
    <input id="register-name" name="name" autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>" aria-describedby="register-name-error" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>" required>
    <p class="field-feedback" id="register-name-error" <?= !isset($errors['name']) ? 'hidden' : '' ?>><span data-error-text><?= e($errors['name'] ?? '') ?></span></p>
  </div>
  <div class="registration-field">
    <label for="register-email">Email</label>
    <input id="register-email" name="email" type="email" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>" aria-describedby="register-email-error" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>" data-email-taken="<?= ($errors['email'] ?? '') === 'Email already registered.' ? 'true' : 'false' ?>" required>
    <p class="field-feedback" id="register-email-error" <?= !isset($errors['email']) ? 'hidden' : '' ?>><span data-error-text><?= e($errors['email'] ?? '') ?></span></p>
  </div>
  <div class="password-field">
    <label for="register-password">Password</label>
    <div class="password-field__control">
      <input id="register-password" type="password" name="password" minlength="8" placeholder="Enter password" autocomplete="new-password" aria-describedby="register-password-requirements register-password-error" aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>" required>
    </div>
    <ul class="password-requirements" id="register-password-requirements">
      <li id="password-length-rule"><span>At least 8 characters</span><span class="validation-sr-only" id="password-rule-status" role="status"> — not met</span></li>
    </ul>
    <p class="field-feedback" id="register-password-error" <?= !isset($errors['password']) ? 'hidden' : '' ?>><span data-error-text><?= e($errors['password'] ?? '') ?></span></p>
  </div>
  <div class="password-field">
    <label for="register-confirm-password">Confirm password</label>
    <div class="password-field__control">
      <input id="register-confirm-password" type="password" name="confirm" placeholder="Enter password" autocomplete="new-password" aria-describedby="register-confirm-error register-password-match" aria-invalid="<?= isset($errors['confirm']) ? 'true' : 'false' ?>" required>
    </div>
    <p class="field-feedback" id="register-confirm-error" <?= !isset($errors['confirm']) ? 'hidden' : '' ?>><span data-error-text><?= e($errors['confirm'] ?? '') ?></span></p>
    <p class="password-match" id="register-password-match" role="status"></p>
  </div>
  <button>Create account</button>
</form>

<script src="assets/Js/register-validation.js" defer></script>
<?php foot(); ?>
