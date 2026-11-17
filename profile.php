<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $error = '';
    $photo = null;
    if (strlen($name) < 2 || strlen($name) > 100 || strlen($phone) > 50) {
        $error = 'Enter a name of 2–100 characters and a phone number of up to 50 characters.';
    }
    $upload = $_FILES['photo'] ?? null;
    if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (!is_int($upload['error'] ?? null) || $upload['error'] !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null)) {
            $error = 'Photo upload failed. Please choose the file again.';
        } elseif ($upload['size'] > 2000000) {
            $error = 'Photo must be under 2 MB.';
        } else {
            $mime = mime_content_type($upload['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !@getimagesize($upload['tmp_name'])) {
                $error = 'Use a valid JPG, PNG or WebP image.';
            } else {
                $photo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($upload['tmp_name']));
            }
        }
    }
    if ($error) {
        flash('Profile update failed: ' . $error);
    } else {
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;
        if ($photo !== null) $_SESSION['user']['photo'] = $photo;
        // Keep the account source in sync for every role.
        foreach ($_SESSION['demo_users'] as &$account) {
            if ($account['id'] !== me()['id']) continue;
            $account['name'] = $name;
            $account['phone'] = $phone;
            if ($photo !== null) $account['photo'] = $photo;
        }
        unset($account);
        $_SESSION['ops_revision'] = ($_SESSION['ops_revision'] ?? 0) + 1;
        flash('Profile updated.');
    }
    header('Location: profile.php');
    exit;
}
head('Profile');
?>
<h1>My profile</h1>
<form method="post" enctype="multipart/form-data" class="card narrow">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <?php if (!empty(me()['photo'])): ?><img class="avatar" src="<?= e(me()['photo']) ?>" alt="Profile photo"><?php endif; ?>
  <label>Profile photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="user"></label>
  <label>Name<input name="name" value="<?= e(me()['name']) ?>" minlength="2" maxlength="100" required></label>
  <label>Email<input value="<?= e(me()['email']) ?>" disabled></label>
  <label>Phone<input name="phone" value="<?= e(me()['phone'] ?? '') ?>" maxlength="50"></label>
  <div class="row"><button type="button" data-rotate>Rotate photo</button><button>Save profile</button></div>
  <p class="hint">Rotation changes the preview only; the original image is saved.</p>
</form>
<?php foot(); ?>
