<?php
header('Cache-Control: private, no-store');
$fines = MyParkData::$records['staff_fines'];
$totals = ['unpaid' => 0, 'paid' => 0, 'void' => 0];
$outstanding = 0;
$collected = 0;
foreach ($fines as $fine) {
    $totals[$fine['status']]++;
    if ($fine['status'] === 'unpaid') $outstanding += $fine['amount_cents'];
    if ($fine['status'] === 'paid') $collected += $fine['paid_amount_cents'] ?? $fine['amount_cents'];
}
$view = ajax_table_view('admin-compounds', $fines, [
    'code' => 'Compound number', 'violation' => 'Violation type', 'location' => 'Parking location',
    'occurred_ts' => 'Issued', 'amount_cents' => 'Amount', 'status' => 'Status', 'actions' => 'Details',
], [
    'order' => 'occurred_ts:desc',
    'filter' => ['label' => 'Compound status', 'field' => 'status', 'choices' => ['' => 'All statuses'] + STAFF_FINE_STATUSES],
    'search_label' => 'Search by compound number, reason or location',
    'empty' => 'No compounds have been issued yet.',
    'numeric' => ['amount_cents'],
]);
head('Compound overview');
?>
<link rel="stylesheet" href="assets/css/compound.css">
<div class="compound-page compound-overview">
  <div class="compound-hero">
    <h1><?= te('Compound overview') ?></h1>
    <p><?= te('Review issued compounds, unpaid balances and recorded payments.') ?></p>
  </div>
  <div class="stats compound-overview-stats">
    <div class="card"><small><?= te('Unpaid compounds') ?></small><b><?= e($totals['unpaid']) ?></b><span><?= e(staff_money($outstanding)) ?></span></div>
    <div class="card"><small><?= te('Paid compounds') ?></small><b><?= e($totals['paid']) ?></b><span><?= e(staff_money($collected)) ?></span></div>
    <div class="card"><small><?= te('Void compounds') ?></small><b><?= e($totals['void']) ?></b></div>
  </div>

  <section class="card" aria-labelledby="admin-compounds-heading">
    <h2 id="admin-compounds-heading"><?= te('Compound records') ?></h2>
    <?php ajax_table_begin($view); ?>
      <div class="table-wrap" role="region" aria-labelledby="admin-compounds-heading" tabindex="0">
        <table class="compound-overview-table">
          <?php ajax_table_head($view); ?>
          <tbody>
          <?php foreach ($view['rows'] as $fine): ?>
            <tr>
              <th scope="row"><?= e($fine['code']) ?></th>
              <td><?= te($fine['violation']) ?></td>
              <td><?= e($fine['location'] ?? '—') ?></td>
              <td><?= e(date('d M Y, H:i', $fine['occurred_ts'])) ?> MYT</td>
              <td class="ajax-table-number"><?= e(staff_money($fine['amount_cents'])) ?></td>
              <td><span class="tag <?= e($fine['status']) ?>"><?= te(STAFF_FINE_STATUSES[$fine['status']]) ?></span></td>
              <td>
                <details class="compound-overview-details">
                  <summary><?= te('View details') ?></summary>
                  <dl>
                    <div><dt><?= te('Vehicle plate') ?></dt><dd><?= e($fine['plate']) ?></dd></div>
                    <div><dt><?= te('Created by') ?></dt><dd><?= e($fine['created_by_name'] ?? '—') ?></dd></div>
                    <?php if (!empty($fine['paid_at'])): ?>
                      <div><dt><?= te('Payment recorded') ?></dt><dd><?= e(date('d M Y, H:i', strtotime($fine['paid_at']))) ?> MYT</dd></div>
                    <?php endif; ?>
                    <?php if (!empty($fine['void_reason'])): ?>
                      <div><dt><?= te('Void reason') ?></dt><dd><?= e($fine['void_reason']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($check = $fine['parking_evidence'] ?? null): ?>
                      <div><dt><?= te('MyPark check at issuance') ?></dt><dd><?= te(STAFF_PARKING_CHECK_LABELS[$check['status']]) ?><br><?= e(date('d M Y, H:i:s', $check['checked_at'])) ?> MYT</dd></div>
                      <?php if ($session = $check['session']): ?>
                        <div><dt><?= te('Related parking session') ?></dt><dd>#<?= e($session['id']) ?> · <?= te($session['status']) ?><br><?= e(date('d M Y, H:i', $session['start'])) ?> – <?= e(date('d M Y, H:i', $session['end'])) ?> MYT</dd></div>
                      <?php endif; ?>
                      <div><dt><?= te('On-site observation') ?></dt><dd><?= e($fine['observation']) ?></dd></div>
                    <?php else: ?>
                      <div><dt><?= te('MyPark check at issuance') ?></dt><dd><?= te('No parking check was recorded.') ?></dd></div>
                    <?php endif; ?>
                    <?php if (!empty($fine['notes'])): ?>
                      <div><dt><?= te('Notes') ?></dt><dd><?= e($fine['notes']) ?></dd></div>
                    <?php endif; ?>
                  </dl>
                </details>
              </td>
            </tr>
          <?php endforeach; ajax_table_empty($view); ?>
          </tbody>
        </table>
      </div>
    <?php ajax_table_end($view); ?>
  </section>
</div>
<?php foot(false); ?>
