<section id="parking-sessions" aria-labelledby="parking-sessions-title">
  <?php
  $columns = ['created_at' => 'Created', 'location' => 'Location', 'plate' => 'Plate',
      'duration' => 'Duration', 'fee' => 'Fee', 'status' => 'Status'];
  $defaultOrder = 'created_at:desc';
  $view = ajax_table_view('parking-sessions', $parkingRows, $columns, [
      'values' => static function (array $row): array {
          $booking = $row['booking'];
          $item = $row['receipt'];
          return [
              'location' => $booking['lot'] ?? $item['details']['Location'] ?? '',
              'plate' => $booking['plate'] ?? $item['details']['Vehicle plate'] ?? '',
              'duration' => (float)($booking['hours'] ?? $item['details']['Duration'] ?? 0),
              'fee' => $item['amount_cents'] ?? (int)round($booking['fee'] * 100),
              'status' => $booking['status'] ?? '',
              'created_at' => $row['at'],
          ];
      },
      'filter' => ['label' => 'Status', 'field' => 'status', 'choices' => ['' => 'All statuses',
          'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled']],
      'search_label' => 'Search plate, location or status',
      'order' => $defaultOrder, 'empty' => $datesApplied ? 'No parking sessions for these dates.' : 'No parking history yet.',
  ]);
  $view['order'] = $view['order'] ?: $defaultOrder;
  $view['order_options'] = [];
  foreach ($columns as $field => $label) {
      foreach (['desc' => 'descending', 'asc' => 'ascending'] as $direction => $directionLabel) {
          $view['order_options']["$field:$direction"] = t('Sort by {label}, {direction}', [
              'label' => t($label), 'direction' => t($directionLabel),
          ]);
      }
  }
  $view['tools_label'] = 'Filter and sort';
  $view['tools_open'] = $view['search'] !== '' || $view['selected'] !== '' || $view['order'] !== $defaultOrder;
  $view['size_label'] = 'Cards per page';
  ajax_table_begin($view);
  ?>
    <h2 id="parking-sessions-title"><?=
        count($parkingRows) === count($receipts)
            ? te($view['total'] === 1 ? '{count} receipt found' : '{count} receipts found', ['count' => $view['total']])
            : te('Parking sessions')
    ?></h2>
    <?php if ($bayError): ?>
      <p class="booking-error" role="alert">
        <?= te($bayError) ?>
        <?php if ($bayError === OKU_APPROVAL_REQUIRED): ?><a href="profile.php#oku-parking-verification"><?= te('OKU Parking Verification') ?></a><?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if (!$view['rows']): ?>
      <div class="card"><h3><?= te($view['empty']) ?></h3></div>
    <?php endif; ?>
    <ul class="receipt-list">
      <?php foreach ($view['rows'] as $row): ?>
        <?php
        ['booking' => $booking, 'receipt' => $item, 'at' => $at] = $row;
        $at = $item['at'] ?? $at;
        require __DIR__ . '/receipt-card.php';
        ?>
      <?php endforeach; ?>
    </ul>
  <?php ajax_table_end($view); ?>
</section>
