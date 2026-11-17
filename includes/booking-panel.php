<?php
$selectedLot = array_column($lots, null, 'id')[$draft['lot_id']] ?? null;
$draftHours = filter_var($draft['hours'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
$durationChoice = in_array($draft['hours'], ['1', '2', '3', '4'], true) ? $draft['hours'] : 'custom';
$manualVehicle = !$vehicles || $draft['vehicle_id'] === 'manual';
$bookingContext = parking_resume_fields($draft);
$manageVehiclesUrl = 'profile.php?' . http_build_query(['from' => 'booking'] + $bookingContext) . '#my-vehicles';
?>
<section class="card" id="bookingPanel" aria-labelledby="parking-heading">
  <h2 id="parking-heading" tabindex="-1"><?= te($review ? 'Review your parking session' : 'Start parking') ?></h2>
  <?php if ($review): ?>
    <p><?= te('Parking starts when you select Start parking now. The times below are estimates in Malaysia time.') ?></p>
    <dl class="parking-summary">
      <div><dt><?= te('Location') ?></dt><dd><?= e($review['lot']['name']) ?> · <?= e($review['lot']['city']) ?></dd></div>
      <div><dt><?= te('Vehicle plate') ?></dt><dd><?= e($review['plate']) ?></dd></div>
      <div><dt><?= te('Duration') ?></dt><dd><?= te($review['hours'] === 1 ? '{count} hour' : '{count} hours', ['count' => $review['hours']]) ?></dd></div>
      <div><dt><?= te('Estimated start') ?></dt><dd><?= e(date('d M Y, H:i', $review['reviewed_at'])) ?> MYT</dd></div>
      <div><dt><?= te('Estimated end') ?></dt><dd><?= e(date('d M Y, H:i', $review['reviewed_at'] + $review['hours'] * 3600)) ?> MYT</dd></div>
      <div><dt><?= te('Fee') ?></dt><dd><strong>RM <?= e(number_format($review['fee'], 2)) ?></strong></dd></div>
    </dl>
    <?php if ($review['save_vehicle']): ?><p class="hint"><?= te('This vehicle will also be saved to your account when you start parking.') ?></p><?php endif; ?>
    <div class="parking-actions">
      <form method="post" action="dashboard.php#bookingPanel">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="action" value="edit_parking">
        <input type="hidden" name="review_token" value="<?= e($review['token']) ?>">
        <button class="ghost"><?= te('Edit details') ?></button>
      </form>
      <form method="post" action="dashboard.php#bookingPanel" id="startParkingForm">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="action" value="start_parking">
        <input type="hidden" name="review_token" value="<?= e($review['token']) ?>">
        <button><?= te('Start parking now') ?></button>
      </form>
    </div>
  <?php else: ?>
    <p><?= te('Choose a location, vehicle and duration. Your parking time starts when you confirm.') ?></p>
    <ol class="booking-steps">
      <li><b>1</b> <?= te('Choose a location') ?></li>
      <li><b>2</b> <?= te('Choose a vehicle and duration') ?></li>
      <li><b>3</b> <?= te('Review and start parking') ?></li>
    </ol>
    <p id="bookingError" class="booking-error" role="alert" tabindex="-1" <?= !$bookingError && !$errors ? 'hidden' : '' ?>>
      <?= te($bookingError ?: ($errors ? 'Check the highlighted booking details.' : '')) ?>
    </p>
    <form method="post" action="dashboard.php#bookingPanel" id="bookForm" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="action" value="review_parking">
      <label for="lot_id"><?= te('Location') ?></label>
      <select name="lot_id" id="lot_id" aria-describedby="bookingLocationError" <?= isset($errors['location']) ? 'aria-invalid="true"' : '' ?> required>
        <option value=""><?= te('Select a location') ?></option>
        <?php foreach ($lots as $lot): ?>
          <option value="<?= e($lot['id']) ?>" data-rate="<?= e($lot['rate']) ?>" <?= $draft['lot_id'] === (string)$lot['id'] ? 'selected' : '' ?>><?= e($lot['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="booking-error" id="bookingLocationError" <?= !isset($errors['location']) ? 'hidden' : '' ?>><?= te($errors['location'] ?? '') ?></p>

      <div class="vehicle-toolbar">
        <label for="<?= $vehicles ? 'savedVehicle' : 'plate' ?>"><?= te($vehicles ? 'Vehicle' : 'Vehicle plate') ?></label>
        <?php if ($customer): ?>
          <a id="manageBookingVehicles" href="<?= e($manageVehiclesUrl) ?>"><?= te('Manage vehicles') ?></a>
        <?php endif; ?>
      </div>
      <?php if ($vehicles): ?>
        <select id="savedVehicle" name="vehicle_id" aria-describedby="bookingVehicleError" <?= isset($errors['vehicle']) ? 'aria-invalid="true"' : '' ?> required>
          <option value=""><?= te('Select a saved vehicle') ?></option>
          <?php foreach ($vehicles as $vehicleId => $vehicle): ?>
            <option value="<?= e($vehicleId) ?>" <?= (string)$vehicleId === $draft['vehicle_id'] ? 'selected' : '' ?>>
              <?= e($vehicle['plate']) ?><?= $vehicle['name'] !== '' ? ' · ' . e($vehicle['name']) : '' ?><?= (string)$vehicleId === $defaultVehicle ? ' (' . te('Default') . ')' : '' ?>
            </option>
          <?php endforeach; ?>
          <option value="manual" <?= $manualVehicle ? 'selected' : '' ?>><?= te('Use another vehicle') ?></option>
        </select>
      <?php else: ?>
        <input type="hidden" name="vehicle_id" value="manual">
      <?php endif; ?>
      <p class="booking-error" id="bookingVehicleError" <?= !isset($errors['vehicle']) ? 'hidden' : '' ?>><?= te($errors['vehicle'] ?? '') ?></p>
      <fieldset id="manualVehicle" class="manual-vehicle" <?= !$manualVehicle ? 'hidden disabled' : '' ?>>
        <?php if ($vehicles): ?><label for="plate"><?= te('Vehicle plate') ?></label><?php endif; ?>
        <input name="plate" id="plate" value="<?= e($draft['plate']) ?>" placeholder="SAB 1234" maxlength="10" autocapitalize="characters"
          aria-describedby="bookingPlateError bookingVehicleError" <?= isset($errors['plate']) ? 'aria-invalid="true"' : '' ?> required>
        <p class="booking-error" id="bookingPlateError" <?= !isset($errors['plate']) ? 'hidden' : '' ?>><?= te($errors['plate'] ?? '') ?></p>
        <?php if ($customer): ?>
          <label class="vehicle-save-option"><input type="checkbox" name="save_vehicle" value="1" <?= $manualVehicle && $draft['save_vehicle'] ? 'checked' : '' ?>> <?= te('Save this vehicle to my account') ?></label>
        <?php endif; ?>
      </fieldset>

      <fieldset class="duration-picker" id="durationPicker" hidden>
        <legend><?= te('Parking duration') ?></legend>
        <div class="duration-options">
          <?php foreach (['1' => '1 hour', '2' => '2 hours', '3' => '3 hours', '4' => '4 hours', 'custom' => 'Custom'] as $value => $label): ?>
            <label class="duration-option">
              <input type="radio" name="duration_choice" value="<?= e($value) ?>" <?= $durationChoice === (string)$value ? 'checked' : '' ?> aria-controls="customDuration"><span><?= te($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="duration-custom" id="customDuration">
        <label for="hours"><?= te('Duration (hours)') ?></label>
        <input type="number" name="hours" id="hours" min="1" max="24" step="1" value="<?= e($draft['hours']) ?>" aria-describedby="durationHint bookingHoursError" <?= isset($errors['hours']) ? 'aria-invalid="true"' : '' ?> required>
        <small id="durationHint"><?= te('Choose a whole number from 1 to 24 hours.') ?></small>
      </div>
      <p class="booking-error" id="bookingHoursError" <?= !isset($errors['hours']) ? 'hidden' : '' ?>><?= te($errors['hours'] ?? '') ?></p>
      <p><?= te('Estimated fee') ?>: <b id="fee" aria-live="polite"><?php
        if ($selectedLot === null) {
            echo te('Select a location to calculate the fee');
        } elseif ($draftHours === false) {
            echo te('Enter a valid duration to calculate the fee');
        } else {
            echo 'RM ' . e(number_format(round($selectedLot['rate'] * $draftHours, 2), 2));
        }
      ?></b></p>
      <button><?= te('Review parking details') ?></button>
    </form>

    <?php if ($customer): ?>
      <dialog class="parking-vehicle-dialog" id="bookingVehicleDialog" aria-labelledby="bookingVehicleDialogTitle" <?= $addVehicleError ? 'data-reopen' : '' ?>>
        <h2 id="bookingVehicleDialogTitle"><?= te('Add vehicle') ?></h2>
        <form method="post" action="dashboard.php#bookingPanel" id="addBookingVehicleForm">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="action" value="add_booking_vehicle">
          <input type="hidden" name="lot_id" value="<?= e($bookingContext['lot_id'] ?? '') ?>">
          <input type="hidden" name="hours" value="<?= e($bookingContext['hours'] ?? '') ?>">
          <?php if ($addVehicleError): ?><p class="booking-error" role="alert"><?= te($addVehicleError) ?></p><?php endif; ?>
          <label for="booking-new-plate"><?= te('Vehicle plate') ?></label>
          <input id="booking-new-plate" name="plate" value="<?= te($addVehicleError ? ($_POST['plate'] ?? '') : '') ?>" placeholder="SAB 1234" maxlength="10" required>
          <label for="booking-new-name"><?= te('Vehicle name (optional)') ?></label>
          <input id="booking-new-name" name="vehicle_name" value="<?= te($addVehicleError ? ($_POST['vehicle_name'] ?? '') : '') ?>" maxlength="40">
          <div class="parking-actions">
            <button type="submit"><?= te('Add vehicle') ?></button>
            <button type="button" class="ghost" data-close-vehicle-dialog><?= te('Cancel') ?></button>
          </div>
          <a class="vehicle-secondary-link" id="manageVehiclesInProfile" href="<?= e($manageVehiclesUrl) ?>"><?= te('Manage vehicles in Profile') ?></a>
        </form>
      </dialog>
      <?php if ($addVehicleError): ?><noscript><p class="booking-error" role="alert"><?= te($addVehicleError) ?></p></noscript><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<script src="assets/js/parking-booking.js" defer></script>
