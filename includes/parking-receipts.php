<section id="parking-sessions" aria-labelledby="parking-sessions-title">
  <h2 id="parking-sessions-title"><?= te('Parking sessions') ?></h2>
  <?php if ($bayError): ?>
    <p class="booking-error" role="alert">
      <?= te($bayError) ?>
      <?php if ($bayError === OKU_APPROVAL_REQUIRED): ?><a href="profile.php#oku-parking-verification"><?= te('OKU Parking Verification') ?></a><?php endif; ?>
    </p>
  <?php endif; ?>

  <?php
  $view = ajax_table_view('parking-sessions', $parkingRows, [
      'location' => 'Location', 'plate' => 'Plate', 'duration' => 'Duration', 'fee' => 'Fee', 'status' => 'Status',
      'created_at' => 'Created', 'actions' => 'Actions',
  ], [
      'values' => static function (array $row): array {
          $booking = $row['booking'];
          $item = $row['receipt'];
          return [
              'location' => $booking['lot'] ?? $item['details']['Location'],
              'plate' => $booking['plate'] ?? $item['details']['Vehicle plate'],
              'duration' => (float)($booking['hours'] ?? $item['details']['Duration'] ?? 0),
              'fee' => $booking ? (int)round($booking['fee'] * 100) : $item['amount_cents'],
              'status' => $booking['status'] ?? '',
              'created_at' => $row['at'],
          ];
      },
      'filter' => ['label' => 'Status', 'field' => 'status', 'choices' => ['' => 'All statuses',
          'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled']],
      'search_label' => 'Search plate, location or status', 'numeric' => ['duration', 'fee'],
      'order' => 'created_at:desc', 'empty' => $datesApplied ? 'No parking sessions for these dates.' : 'No parking history yet.',
  ]);
  ajax_table_begin($view);
  ?>
    <div class="table-wrap">
      <table id="parkingSessionsTable">
        <?php ajax_table_head($view); ?>
        <tbody>
          <?php foreach ($view['rows'] as $row):
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
            <tr data-parking-session data-status="<?= e($status) ?>" data-plate="<?= e($plate) ?>">
              <td><?= e($location) ?><?php if ($bayType !== null): ?><br><small><?= te(PARKING_BAY_TYPES[$bayType]) ?></small><?php endif; ?></td>
              <td><?= e($plate) ?></td>
              <td class="ajax-table-number"><?= e($duration) ?></td>
              <td class="ajax-table-number"><?= e(receipt_money($fee)) ?></td>
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
          <?php ajax_table_empty($view); ?>
        </tbody>
      </table>
    </div>
  <?php ajax_table_end($view); ?>
</section>
