<section id="parking-sessions" aria-labelledby="parking-sessions-title">
  <h2 id="parking-sessions-title"><?= te('Parking sessions') ?></h2>
  <?php if ($bayError): ?>
    <p class="booking-error" role="alert">
      <?= te($bayError) ?>
      <?php if ($bayError === OKU_APPROVAL_REQUIRED): ?><a href="profile.php#oku-parking-verification"><?= te('OKU Parking Verification') ?></a><?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if ($parkingRows): ?>
    <div class="row card parking-session-filters">
      <label class="validation-sr-only" for="parkingSessionSearch"><?= te('Search plate, location or status') ?></label>
      <input type="search" id="parkingSessionSearch" placeholder="<?= te('Search plate, location or status') ?>">
      <label class="validation-sr-only" for="parkingSessionStatus"><?= te('Status') ?></label>
      <select id="parkingSessionStatus">
        <option value=""><?= te('All statuses') ?></option>
        <option value="active"><?= te('Active') ?></option>
        <option value="completed"><?= te('Completed') ?></option>
        <option value="cancelled"><?= te('Cancelled') ?></option>
      </select>
    </div>

    <div class="table-wrap">
      <table id="parkingSessionsTable" data-paginated="true">
        <thead><tr><th><?= te('Location') ?></th><th><?= te('Plate') ?></th><th><?= te('Duration') ?></th><th><?= te('Fee') ?></th><th><?= te('Status') ?></th><th><?= te('Created') ?></th><th><?= te('Actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($parkingRows as $row):
              $booking = $row['booking'];
              $item = $row['receipt'];
              $location = $booking['lot'] ?? $item['details']['Location'];
              $plate = $booking['plate'] ?? $item['details']['Vehicle plate'];
              $status = $booking['status'] ?? '';
              $expiry = $status === 'active' ? booking_expiry($booking) : null;
              $bayType = $booking ? parking_booking_bay_type($booking) : null;
              $duration = $booking ? $booking['hours'] . 'h' : $item['details']['Duration'];
              $fee = $booking ? (int) round($booking['fee'] * 100) : $item['amount_cents'];
          ?>
            <tr data-parking-session data-status="<?= e($status) ?>" data-plate="<?= e($plate) ?>" data-search="<?= e($plate . ' ' . $location) ?>">
              <td><?= e($location) ?><?php if ($bayType !== null): ?><br><small><?= te(PARKING_BAY_TYPES[$bayType]) ?></small><?php endif; ?></td>
              <td><?= e($plate) ?></td>
              <td><?= e($duration) ?></td>
              <td><?= e(receipt_money($fee)) ?></td>
              <td>
                <?php if ($booking): ?><span class="tag <?= e($status) ?>"><?= te($status) ?></span><?php else: ?>—<?php endif; ?>
                <?php if ($expiry !== null): ?><span class="parking-timer" data-expires="<?= e($expiry) ?>">--:--:--</span><small class="timer-label"><?= te('Time remaining') ?></small><?php endif; ?>
              </td>
              <td><?= e($booking['created_at'] ?? receipt_time($row['at'])) ?></td>
              <td>
                <div class="parking-session-actions">
                  <?php if ($item): ?><a class="btn" href="<?= e($listUrl . '&id=' . $item['id']) ?>"><?= te('View receipt') ?></a><?php endif; ?>
                  <?php if ($booking) require __DIR__ . '/parking-session-actions.php'; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr data-no-parking-matches hidden><td colspan="7"><?= te('No matching records') ?></td></tr>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="card"><?= te($datesApplied ? 'No parking sessions for these dates.' : 'No parking history yet.') ?></p>
  <?php endif; ?>
</section>
