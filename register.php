<?php
require 'includes.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (strlen($name) < 2) $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== ($_POST['confirm'] ?? '')) $errors[] = 'Passwords do not match.';

    foreach ($_SESSION['demo_users'] as $user) {
        if ($user['email'] === $email) $errors[] = 'Email already registered.';
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

<form method="post" class="card narrow">
  <h2>Create member account</h2>
  <?php foreach ($errors as $error): ?><p class="error"><?= e($error) ?></p><?php endforeach; ?>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <label>Full name<input name="name" required></label>
  <label>Email<input type="email" name="email" required></label>
  <label>Password<input type="password" name="password" minlength="8" required></label>
  <label>Confirm password<input type="password" name="confirm" required></label>
  <button>Create account</button>
</form>

<?php foot(); ?>
