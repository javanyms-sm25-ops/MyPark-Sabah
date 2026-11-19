<?php
$submission = oku_current_submission(me()['id']);
$okuStatus = $submission['status'] ?? 'not_submitted';
$okuUrl = $vehiclesPage . '#oku-parking-verification';
?>
<section class="card narrow" id="oku-parking-verification" aria-labelledby="oku-profile-heading">
  <h2 id="oku-profile-heading"><?= te('OKU Parking Verification') ?></h2>
  <p><?= te('Status') ?> <strong><?= te(OKU_STATUS_LABELS[$okuStatus]) ?></strong></p>
  <?php if ($okuError): ?><p class="booking-error" role="alert"><?= te($okuError) ?></p><?php endif; ?>
  <?php if ($okuStatus === 'pending'): ?>
    <p><?= te('Your proof was submitted for review.') ?></p>
  <?php elseif ($okuStatus === 'approved'): ?>
    <p><?= te('You can now choose an available OKU bay in MyPark Sabah.') ?></p>
  <?php elseif ($okuStatus === 'rejected'): ?>
    <p><strong><?= te('Rejection reason') ?></strong></p>
    <p class="oku-rejection-reason"><?= e($submission['reason']) ?></p>
  <?php endif; ?>
  <?php if (in_array($okuStatus, ['not_submitted', 'rejected'], true)): ?>
    <form method="post" enctype="multipart/form-data" action="<?= e($okuUrl) ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="oku_action" value="submit">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= OKU_MAX_PROOF_BYTES ?>">
      <label for="oku-proof"><?= te('OKU parking proof') ?></label>
      <input type="file" id="oku-proof" name="oku_proof" accept="<?= e(implode(',', array_keys(OKU_PROOF_TYPES))) ?>" aria-describedby="oku-proof-help" required>
      <p class="hint" id="oku-proof-help"><?= te('Demo only. Upload a fictional JPG, PNG or WebP image up to 2 MB.') ?></p>
      <button type="submit"><?= te($okuStatus === 'rejected' ? 'Resubmit proof for review' : 'Submit proof for review') ?></button>
    </form>
  <?php endif; ?>
  <?php if ($bookingContext || $okuStatus === 'approved'): ?>
    <p><a href="<?= e($bookingContext ? 'parking.php?' . http_build_query(parking_resume_fields($bookingContext)) : 'dashboard.php#locations') ?>"><?= te('Back to parking') ?></a></p>
  <?php endif; ?>
</section>
