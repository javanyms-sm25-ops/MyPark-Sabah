<ul class="find-parking-list" id="find-parking-list">
  <?php foreach ($locations as $location): ?>
    <?php
    ['lot' => $lot, 'bays' => $bays, 'search' => $searchText, 'matches' => $matches] = $location;
    $lat = filter_var($lot['lat'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($lot['lng'] ?? null, FILTER_VALIDATE_FLOAT);
    $hasPosition = $lat !== false && $lng !== false && abs($lat) <= 90 && abs($lng) <= 180;
    ?>
    <li class="find-parking-location" data-parking-location="<?= e($lot['id']) ?>"
        data-search="<?= e($searchText) ?>" data-regular-available="<?= e($bays['regular']['available']) ?>"
        data-oku-capacity="<?= e($bays['oku']['capacity']) ?>" data-oku-available="<?= e($bays['oku']['available']) ?>"
        <?php if ($hasPosition): ?>data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>"<?php endif; ?>
        <?= $matches ? '' : 'hidden' ?>>
      <div class="find-parking-location-heading">
        <h3><?= e($lot['name']) ?></h3>
        <?php if ($canBook): ?>
          <button type="button" class="find-parking-favourite" data-wish="<?= e($lot['id']) ?>" aria-pressed="false"
                  aria-label="<?= te('Favourite {location}', ['location' => $lot['name']]) ?>" hidden>♡</button>
        <?php endif; ?>
      </div>
      <p class="find-parking-address"><?= e(implode(' · ', array_filter([$lot['address'] ?? '', $lot['city'] ?? '']))) ?></p>
      <?php if ($hasPosition): ?><p class="find-parking-distance" data-parking-distance hidden></p><?php endif; ?>
      <dl class="find-parking-availability">
        <div data-parking-capacity-unknown <?= $bays['regular']['capacity'] === null ? '' : 'hidden' ?>>
          <dt><?= te('Parking availability') ?></dt>
          <dd class="find-parking-status"><?= te('Capacity unavailable') ?></dd>
        </div>
        <?php foreach (['regular' => 'Regular bays', 'oku' => 'OKU bays'] as $type => $label):
            $bay = $bays[$type];
            $state = match (true) {
                !$bay['capacity'] => 'none',
                $bay['available'] === 0 => 'full',
                default => 'available',
            };
            $status = match ($state) {
                'none' => t('Not offered'),
                'full' => t('Full'),
                default => t('{count} available', ['count' => $bay['available']]),
            };
        ?>
          <div data-parking-bay="<?= e($type) ?>" <?= $bay['capacity'] === null ? 'hidden' : '' ?>>
            <dt><?= te($label) ?><?php if ($type === 'oku'): ?><span data-parking-oku-rate <?= $bay['capacity'] > 0 ? '' : 'hidden' ?>> · <?= te('Free') ?></span><?php endif; ?></dt>
            <dd class="find-parking-status" data-bay-status="<?= e($state) ?>"><?= e($status) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
      <p class="find-parking-rate"><?= te('Regular parking') ?> · <?= e(parking_price_label()) ?></p>
      <div class="find-parking-actions">
        <?php if ($canBook): ?>
          <a class="btn" href="<?= e('parking-details.php?' . http_build_query(['lot_id' => $lot['id']] + ($okuOnly ? ['bay_type' => 'oku'] : []))) ?>"
             data-select-parking><?= te('Select location') ?></a>
        <?php endif; ?>
        <?php if ($hasPosition): ?>
          <button class="btn btn-secondary" type="button" data-show-on-map aria-pressed="false"
                  aria-controls="find-parking-map" hidden><?= te('Show on map') ?></button>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
