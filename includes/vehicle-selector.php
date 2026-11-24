<?php
$selectedVehicleId = $vehicleState['selected_id'];
$selectedVehicle = $vehicleState['vehicles'][$selectedVehicleId ?? ''] ?? null;
?>
<section class="card selected-vehicle" id="selected-vehicle" aria-labelledby="selected-vehicle-title">
  <div class="selected-vehicle__identity">
    <h2 id="selected-vehicle-title"><?= te('Selected vehicle') ?></h2>
    <?php if ($selectedVehicle): ?>
      <strong class="selected-vehicle__plate" id="selected-vehicle-plate"><?= e($selectedVehicle['plate']) ?></strong>
      <span class="muted" id="selected-vehicle-name"<?= $selectedVehicle['name'] === '' ? ' hidden' : '' ?>><?= e($selectedVehicle['name']) ?></span>
    <?php else: ?>
      <p class="muted"><?= te('Add your vehicle to start parking') ?></p>
    <?php endif; ?>
  </div>
  <div class="selected-vehicle__controls">
    <?php if ($vehicleState['vehicles']): ?>
      <form method="post" action="dashboard.php#selected-vehicle" id="vehicle-selection-form" data-endpoint="api/vehicles.php">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="action" value="select_vehicle">
        <label for="parking-vehicle-selector"><?= te('Switch vehicle') ?></label>
        <div class="selected-vehicle__picker">
          <select id="parking-vehicle-selector" name="vehicle_id" required>
            <?php foreach ($vehicleState['vehicles'] as $id => $vehicle): ?>
              <option value="<?= e($id) ?>" data-plate="<?= e($vehicle['plate']) ?>" data-name="<?= e($vehicle['name']) ?>"<?= (string)$id === $selectedVehicleId ? ' selected' : '' ?>>
                <?= e($vehicle['plate']) ?><?= $vehicle['name'] !== '' ? ' · ' . e($vehicle['name']) : '' ?><?= (string)$id === $vehicleState['default_id'] ? ' (' . te('Default') . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="ghost" data-save-selection><?= te('Use vehicle') ?></button>
        </div>
      </form>
    <?php endif; ?>
    <div class="parking-actions">
      <a class="btn" href="profile.php?from=parking&amp;vehicle_edit=new#vehicle-editor"><?= te($selectedVehicle ? 'Add vehicle' : 'Add your vehicle to start parking') ?></a>
      <a class="btn btn-secondary vehicle-secondary-link" href="profile.php#my-vehicles"><?= te('Manage vehicles') ?></a>
    </div>
  </div>
  <p class="selected-vehicle__feedback hint" id="vehicle-selection-status" role="status" aria-live="polite"></p>
  <p class="selected-vehicle__feedback booking-error" id="vehicle-selection-error" role="alert"<?= !$vehicleSelectionError ? ' hidden' : '' ?>><?= te($vehicleSelectionError) ?></p>
</section>
<script src="assets/js/vehicles.js" defer></script>
