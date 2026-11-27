<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
require __DIR__ . '/includes/parking-booking.php';
require_once __DIR__ . '/includes/oku-verification.php';

// Older open Profile forms still submit their proof to the dedicated page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oku_action'])) {
    require __DIR__ . '/oku-verification.php';
    return;
}

$fromParking = ($_GET['from'] ?? '') === 'parking';
$bookingContext = ($_GET['from'] ?? '') === 'booking' ? ['from' => 'booking'] + parking_resume_fields($_GET) : ($fromParking ? ['from' => 'parking'] : []);
$profilePage = 'profile.php' . ($bookingContext ? '?' . http_build_query($bookingContext) : '');
$editProfilePage = 'profile.php?' . http_build_query($bookingContext + ['edit' => '1']);
$editingProfile = ($_GET['edit'] ?? '') === '1';
$profileError = '';
$name = me()['name'];
$phone = me()['phone'] ?? '';
$editor = is_string($_GET['vehicle_edit'] ?? null) ? $_GET['vehicle_edit'] : '';
$vehicleError = '';
$vehicleFormInput = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_action'])) {
    if (me()['role'] !== 'user') {
        http_response_code(403);
        exit('Vehicle management is available to customers.');
    }
    try {
        $updated = change_saved_vehicle(me()['id'], $_POST);
        if ($fromParking && $_POST['vehicle_action'] === 'add') {
            change_saved_vehicle(me()['id'], ['vehicle_action' => 'select', 'vehicle_id' => $updated['id']]);
            flash($updated['message']);
            header('Location: dashboard.php#selected-vehicle', true, 303);
            exit;
        }
        flash($updated['message']);
        header('Location: ' . $profilePage . '#my-vehicles', true, 303);
        exit;
    } catch (InvalidArgumentException | RuntimeException $error) {
        $vehicleError = $error->getMessage();
        $vehicleFormInput = ['plate' => $_POST['plate'] ?? '', 'name' => $_POST['vehicle_name'] ?? ''];
        $editor = match ($_POST['vehicle_action']) {
            'add' => 'new',
            'edit' => $_POST['vehicle_id'] ?? '',
            default => '',
        };
        http_response_code($error->getCode() ?: 422);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $profileError = $error;
        $editingProfile = true;
        http_response_code(422);
    } else {
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;
        if ($photo !== null) $_SESSION['user']['photo'] = $photo;
        // Keep the account source in sync for every role.
        foreach (MyParkData::$records['demo_users'] as &$account) {
            if ($account['id'] !== me()['id']) continue;
            $account['name'] = $name;
            $account['phone'] = $phone;
            if ($photo !== null) $account['photo'] = $photo;
        }
        unset($account);
        MyParkData::$records['ops_revision'] = (MyParkData::$records['ops_revision'] ?? 0) + 1;
        flash('Profile updated.');
        header('Location: ' . $profilePage, true, 303);
        exit;
    }
}
$vehicles = [];
$defaultVehicle = null;
header('Cache-Control: private, no-store');
if (me()['role'] === 'user' && !$editingProfile) {
    try {
        $vehicleState = saved_vehicle_state(me()['id']);
        $vehicles = $vehicleState['vehicles'];
        $defaultVehicle = $vehicleState['default_id'];
    } catch (RuntimeException $error) {
        $vehicleError = $error->getMessage();
        http_response_code(503);
    }
}
$profile = me();
$profilePhoto = $profile['photo'] ?? '';
$profileRole = match ($profile['role']) {
    'user' => 'Member',
    'superadmin' => 'Super Admin',
    default => ucfirst($profile['role']),
};
preg_match('/[\p{L}\p{N}]/u', $profile['name'], $profileInitial);
head($editingProfile ? 'Edit profile' : 'Profile');
?>
<link rel="stylesheet" href="assets/css/profile.css">
<div class="profile-page">
<?php if ($editingProfile): ?>
<h1 class="profile-page-title"><?= te('Edit profile') ?></h1>
<form method="post" action="<?= e($editProfilePage) ?>" enctype="multipart/form-data" class="card profile-editor">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <?php if ($profileError !== ''): ?>
    <p class="profile-editor-error" role="alert"><?= te($profileError) ?></p>
  <?php endif; ?>
  <section class="profile-photo-editor" data-photo-drop aria-labelledby="profile-photo-title">
      <div class="profile-avatar-frame">
        <img class="profile-avatar" data-photo-preview alt="<?= te('Profile photo') ?>" width="80" height="80"
          <?php if ($profilePhoto): ?>src="<?= e($profilePhoto) ?>"<?php else: ?>hidden<?php endif; ?>>
        <span class="profile-avatar-initial" data-photo-initial aria-hidden="true" <?= $profilePhoto ? 'hidden' : '' ?>><?= e(strtoupper($profileInitial[0] ?? 'P')) ?></span>
      </div>
      <div class="profile-photo-content">
        <h2 id="profile-photo-title"><?= te('Profile photo') ?></h2>
        <div class="profile-photo-controls">
          <input class="validation-sr-only" id="profilePhoto" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
            aria-describedby="profilePhotoHint profilePhotoFeedback">
          <label for="profilePhoto" class="btn btn-secondary profile-photo-action"><?= te('Choose photo') ?></label>
          <button type="button" class="ghost profile-photo-action" data-rotate hidden><?= te('Rotate photo') ?></button>
        </div>
        <p class="profile-photo-hint" id="profilePhotoHint"><?= te('JPG, PNG or WebP, up to 2 MB.') ?> <span class="profile-drop-hint"><?= te('You can also drag and drop a photo here.') ?></span></p>
        <p class="profile-photo-filename" data-photo-filename role="status"></p>
        <p class="field-feedback" id="profilePhotoFeedback" aria-live="polite" aria-atomic="true"></p>
      </div>
  </section>
  <section class="profile-edit-details" aria-labelledby="profile-details-title">
    <h2 id="profile-details-title"><?= te('Account details') ?></h2>
    <div class="profile-fields">
      <div class="profile-field">
        <label for="profile-name"><?= te('Name') ?></label>
        <input id="profile-name" name="name" value="<?= e($name) ?>" autocomplete="name" minlength="2" maxlength="100" required>
      </div>
      <div class="profile-field">
        <label for="profile-phone"><?= te('Phone') ?></label>
        <input id="profile-phone" type="tel" name="phone" value="<?= e($phone) ?>" autocomplete="tel" maxlength="50">
      </div>
    </div>
  </section>
  <div class="profile-save">
    <a class="btn btn-secondary" href="<?= e($profilePage) ?>"><?= te('Cancel') ?></a>
    <button type="submit"><?= te('Save profile') ?></button>
  </div>
</form>
<?php else: ?>
<h1 class="profile-page-title"><?= te('My profile') ?></h1>
<section class="profile-hero" aria-labelledby="profile-identity">
  <div class="profile-avatar-frame">
    <?php if ($profilePhoto): ?>
      <img class="profile-avatar" src="<?= e($profilePhoto) ?>" width="96" height="96" alt="<?= te('Profile photo') ?>">
    <?php else: ?>
      <span class="profile-avatar-initial" aria-hidden="true"><?= e(strtoupper($profileInitial[0] ?? 'P')) ?></span>
    <?php endif; ?>
  </div>
  <div class="profile-identity">
    <h2 id="profile-identity"><?= e($profile['name']) ?></h2>
    <span class="profile-role"><?= te($profileRole) ?></span>
  </div>
  <a class="btn profile-edit-link" href="<?= e($editProfilePage) ?>"><?= te('Edit profile') ?></a>
</section>
<div class="profile-overview<?= $profile['role'] === 'user' ? ' profile-overview--member' : '' ?>">
  <section class="card profile-details-card" aria-labelledby="profile-details-title">
    <h2 id="profile-details-title"><?= te('Account details') ?></h2>
    <dl class="profile-info">
      <div><dt><?= te('Name') ?></dt><dd><?= e($profile['name']) ?></dd></div>
      <div><dt><?= te('Phone') ?></dt><dd><?= $phone !== '' ? e($phone) : te('Not added') ?></dd></div>
      <div>
        <dt><?= te('Email') ?></dt>
        <dd><?= e($profile['email']) ?></dd>
      </div>
    </dl>
    <a class="profile-account-link" href="pages/change-email.php"><?= te('Change email address') ?> <span aria-hidden="true">→</span></a>
    <a class="profile-account-link" href="pages/change-password.php"><?= te('Change password') ?> <span aria-hidden="true">→</span></a>
  </section>
<?php if ($profile['role'] === 'user'): ?>
  <div class="profile-member-tools">
  <?php
    $editVehicle = $vehicles[$editor] ?? null;
    $editing = $editor === 'new' || $editVehicle !== null;
    $initialVehicle = $vehicleFormInput ?? $editVehicle ?? ['plate' => '', 'name' => ''];
    $editorUrl = 'profile.php?' . http_build_query($bookingContext + ['vehicle_edit' => $editor]) . '#vehicle-editor';
  ?>
  <section class="card profile-vehicles-card" id="my-vehicles" aria-labelledby="my-vehicles-title">
    <div class="vehicle-toolbar">
      <h2 id="my-vehicles-title"><?= te('My vehicles') ?></h2>
      <a class="btn" href="<?= e('profile.php?' . http_build_query($bookingContext + ['vehicle_edit' => 'new']) . '#vehicle-editor') ?>"><?= te('Add vehicle') ?></a>
    </div>
    <?php if ($bookingContext): ?>
      <p><a class="vehicle-secondary-link" href="<?= e($fromParking ? 'dashboard.php#selected-vehicle' : 'parking-details.php?' . http_build_query(parking_resume_fields($bookingContext))) ?>"><?= te('Back to parking') ?></a></p>
    <?php endif; ?>
    <?php if ($vehicleError): ?><p class="booking-error" role="alert"><?= te($vehicleError) ?></p><?php endif; ?>
    <?php if ($editing): ?>
      <form class="vehicle-editor" method="post" action="<?= e($editorUrl) ?>" id="vehicle-editor" aria-labelledby="vehicle-editor-title">
        <h3 id="vehicle-editor-title"><?= te($editVehicle === null ? 'Add vehicle' : 'Edit vehicle') ?></h3>
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="vehicle_action" value="<?= $editVehicle === null ? 'add' : 'edit' ?>">
        <?php if ($editVehicle !== null): ?><input type="hidden" name="vehicle_id" value="<?= e($editor) ?>"><?php endif; ?>
        <label for="profile-vehicle-plate"><?= te('Vehicle plate') ?></label>
        <input id="profile-vehicle-plate" name="plate" value="<?= e($initialVehicle['plate']) ?>" placeholder="SAB 1234" maxlength="20" autocapitalize="characters" required>
        <label for="profile-vehicle-name"><?= te('Vehicle name (optional)') ?></label>
        <input id="profile-vehicle-name" name="vehicle_name" value="<?= e($initialVehicle['name']) ?>" maxlength="40">
        <div class="parking-actions">
          <button><?= te('Save') ?></button>
          <a class="vehicle-secondary-link" href="<?= e($profilePage . '#my-vehicles') ?>"><?= te('Cancel') ?></a>
        </div>
      </form>
    <?php endif; ?>
    <?php if (!$vehicles): ?><p class="profile-empty-state"><?= te('No saved vehicles yet.') ?></p><?php endif; ?>
    <?php if ($vehicles): ?>
      <ul class="vehicle-list">
        <?php foreach ($vehicles as $vehicleId => $vehicle): ?>
          <?php $isDefault = (string)$vehicleId === $defaultVehicle; ?>
          <li class="vehicle-list-item">
            <div>
              <strong><?= e($vehicle['plate']) ?></strong>
              <?php if ($isDefault): ?><span class="tag active"><?= te('Default') ?></span><?php endif; ?>
              <?php if ($vehicle['name'] !== ''): ?><div class="muted"><?= e($vehicle['name']) ?></div><?php endif; ?>
            </div>
            <div class="parking-actions">
              <a class="vehicle-secondary-link" href="<?= e('profile.php?' . http_build_query($bookingContext + ['vehicle_edit' => $vehicleId]) . '#vehicle-editor') ?>" aria-label="<?= te('Edit {plate}', ['plate' => $vehicle['plate']]) ?>"><?= te('Edit') ?></a>
              <form method="post" action="<?= e($profilePage . '#my-vehicles') ?>" class="parking-actions">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="vehicle_id" value="<?= e($vehicleId) ?>">
                <?php if (!$isDefault): ?>
                  <button class="ghost" name="vehicle_action" value="default" aria-label="<?= te('Set {plate} as default', ['plate' => $vehicle['plate']]) ?>"><?= te('Set as default') ?></button>
                <?php endif; ?>
                <button class="danger" name="vehicle_action" value="delete" aria-label="<?= te('Remove {plate}', ['plate' => $vehicle['plate']]) ?>" data-confirm="<?= te('Remove {plate} from your saved vehicles?', ['plate' => $vehicle['plate']]) ?>"><?= te('Remove') ?></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php require __DIR__ . '/includes/oku-profile.php'; ?>
  </div>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
<script src="assets/js/vehicles.js" defer></script>
<?php foot(showChatbot: me()['role'] !== 'user'); ?>
