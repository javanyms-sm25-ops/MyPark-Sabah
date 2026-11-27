<?php
$submission = oku_current_submission(me()['id']);
$okuStatus = $submission['status'] ?? 'not_submitted';
$okuBadge = match ($okuStatus) {
    'pending' => 'pending',
    'approved' => 'available',
    'rejected' => 'cancelled',
    default => '',
};
$okuUrl = 'oku-verification.php' . ($bookingContext ? '?' . http_build_query($bookingContext) : '');
?>
<section class="card profile-oku-card" id="oku-parking-verification" aria-labelledby="oku-profile-heading">
  <div class="profile-oku-heading">
    <img src="assets/images/oku-parking.png" width="40" height="40" alt="">
    <h2 id="oku-profile-heading"><?= te('OKU Parking Verification') ?></h2>
  </div>
  <p class="profile-oku-status"><?= te('Status') ?> <span class="tag <?= e($okuBadge) ?>"><?= te(OKU_STATUS_LABELS[$okuStatus]) ?></span></p>
  <a class="btn btn-secondary" href="<?= e($okuUrl) ?>"><?= te('Open verification page') ?></a>
</section>
