<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
require __DIR__ . '/includes/parking-booking.php';

$bookingContext = ($_GET['from'] ?? '') === 'booking' ? ['from' => 'booking'] + parking_resume_fields($_GET) : [];
$vehiclesPage = 'profile.php' . ($bookingContext ? '?' . http_build_query($bookingContext) : '');
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
        flash($updated['message']);
        header('Location: ' . $vehiclesPage . '#my-vehicles', true, 303);
        exit;
    } catch (InvalidArgumentException $error) {
        $vehicleError = $error->getMessage();
        $vehicleFormInput = ['plate' => $_POST['plate'] ?? '', 'name' => $_POST['vehicle_name'] ?? ''];
        $editor = match ($_POST['vehicle_action']) {
            'add' => 'new',
            'edit' => $_POST['vehicle_id'] ?? '',
            default => '',
        };
        http_response_code(422);
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
<h1><?= te('My profile') ?></h1>
<form method="post" enctype="multipart/form-data" class="card narrow">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <?php if (!empty(me()['photo'])): ?><img class="avatar" src="<?= e(me()['photo']) ?>" alt="Profile photo"><?php endif; ?>
  <label><?= te('Profile photo') ?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="user"></label>
  <label><?= te('Name') ?><input name="name" value="<?= e(me()['name']) ?>" minlength="2" maxlength="100" required></label>
  <label><?= te('Email') ?><input value="<?= e(me()['email']) ?>" disabled></label>
  <label><?= te('Phone') ?><input name="phone" value="<?= e(me()['phone'] ?? '') ?>" maxlength="50"></label>
  <div class="row"><button type="button" data-rotate><?= te('Rotate photo') ?></button><button><?= te('Save profile') ?></button></div>
  <p class="hint"><?= te('Rotation changes the preview only; the original image is saved.') ?></p>
</form>
<?php if (me()['role'] === 'user'): ?>
  <?php
    $vehicles = saved_vehicles(me()['id']);
    $defaultVehicle = default_vehicle_id(me()['id']);
    $editVehicle = $vehicles[$editor] ?? null;
    $editing = $editor === 'new' || $editVehicle !== null;
    $initialVehicle = $vehicleFormInput ?? $editVehicle ?? ['plate' => '', 'name' => ''];
    $editorUrl = 'profile.php?' . http_build_query($bookingContext + ['vehicle_edit' => $editor]) . '#vehicle-editor';
  ?>
  <section class="card narrow" id="my-vehicles" aria-labelledby="my-vehicles-title">
    <div class="vehicle-toolbar">
      <h2 id="my-vehicles-title"><?= te('My vehicles') ?></h2>
      <a class="btn" href="<?= e('profile.php?' . http_build_query($bookingContext + ['vehicle_edit' => 'new']) . '#vehicle-editor') ?>"><?= te('Add vehicle') ?></a>
    </div>
    <?php if ($bookingContext): ?>
      <p><a class="vehicle-secondary-link" href="<?= e('dashboard.php?' . http_build_query(parking_resume_fields($bookingContext)) . '#bookingPanel') ?>"><?= te('Back to parking') ?></a></p>
    <?php endif; ?>
    <?php if ($vehicleError): ?><p class="booking-error" role="alert"><?= te($vehicleError) ?></p><?php endif; ?>
    <?php if ($editing): ?>
      <form class="vehicle-editor" method="post" action="<?= e($editorUrl) ?>" id="vehicle-editor" aria-labelledby="vehicle-editor-title">
        <h3 id="vehicle-editor-title"><?= te($editVehicle === null ? 'Add vehicle' : 'Edit vehicle') ?></h3>
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="vehicle_action" value="<?= $editVehicle === null ? 'add' : 'edit' ?>">
        <?php if ($editVehicle !== null): ?><input type="hidden" name="vehicle_id" value="<?= e($editor) ?>"><?php endif; ?>
        <label for="profile-vehicle-plate"><?= te('Vehicle plate') ?></label>
        <input id="profile-vehicle-plate" name="plate" value="<?= e($initialVehicle['plate']) ?>" placeholder="SAB 1234" maxlength="10" required>
        <label for="profile-vehicle-name"><?= te('Vehicle name (optional)') ?></label>
        <input id="profile-vehicle-name" name="vehicle_name" value="<?= e($initialVehicle['name']) ?>" maxlength="40">
        <div class="parking-actions">
          <button><?= te('Save') ?></button>
          <a class="vehicle-secondary-link" href="<?= e($vehiclesPage . '#my-vehicles') ?>"><?= te('Cancel') ?></a>
        </div>
      </form>
    <?php endif; ?>
    <?php if (!$vehicles): ?><p><?= te('No saved vehicles yet.') ?></p><?php endif; ?>
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
              <a class="vehicle-secondary-link" href="<?= e('profile.php?' . http_build_query($bookingContext + ['vehicle_edit' => $vehicleId]) . '#vehicle-editor') ?>" aria-label="Edit <?= e($vehicle['plate']) ?>"><?= te('Edit') ?></a>
              <form method="post" action="<?= e($vehiclesPage . '#my-vehicles') ?>" class="parking-actions">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="vehicle_id" value="<?= e($vehicleId) ?>">
                <?php if (!$isDefault): ?>
                  <button class="ghost" name="vehicle_action" value="default" aria-label="Set <?= e($vehicle['plate']) ?> as default"><?= te('Set as default') ?></button>
                <?php endif; ?>
                <button class="danger" name="vehicle_action" value="delete" aria-label="Remove <?= e($vehicle['plate']) ?>" data-confirm="Remove <?= e($vehicle['plate']) ?> from your saved vehicles?"><?= te('Remove') ?></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php foot(); ?>
