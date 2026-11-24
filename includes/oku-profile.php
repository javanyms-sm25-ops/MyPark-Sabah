<?php
$submission = oku_current_submission(me()['id']);
$okuStatus = $submission['status'] ?? 'not_submitted';
$okuUrl = 'oku-verification.php' . ($bookingContext ? '?' . http_build_query($bookingContext) : '');
?>
<section class="card narrow oku-profile-card" id="oku-parking-verification" aria-labelledby="oku-profile-heading">
  <img class="oku-verification-logo" src="assets/images/oku-parking.png" width="64" height="64" alt="<?= te('OKU parking symbol') ?>">
  <h2 id="oku-profile-heading"><?= te('OKU Parking Verification') ?></h2>
  <p><?= te('Status') ?> <strong><?= te(OKU_STATUS_LABELS[$okuStatus]) ?></strong></p>
  <a class="btn" href="<?= e($okuUrl) ?>"><?= te('Open verification page') ?></a>
</section>
