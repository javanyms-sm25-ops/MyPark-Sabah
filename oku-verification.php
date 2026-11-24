<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/parking-booking.php';

if (me()['role'] !== 'user') {
    http_response_code(403);
    exit(te('Only members can submit OKU parking proof.'));
}
header('Cache-Control: private, no-store');

$bookingContext = ($_GET['from'] ?? '') === 'booking'
    ? ['from' => 'booking'] + parking_resume_fields($_GET) : [];
$query = $bookingContext ? '?' . http_build_query($bookingContext) : '';
$verificationPage = 'oku-verification.php' . $query;
$profileUrl = 'profile.php' . $query . '#oku-parking-verification';
$parkingUrl = $bookingContext
    ? 'parking-details.php?' . http_build_query(parking_resume_fields($bookingContext)) : 'dashboard.php#locations';
$okuError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if (($_POST['oku_action'] ?? '') !== 'submit') throw new RuntimeException('Unknown OKU operation.', 400);
        oku_submit(me(), $_FILES['oku_proof'] ?? []);
        flash('OKU proof submitted for review.');
        header('Location: ' . $verificationPage, true, 303);
        exit;
    } catch (RuntimeException $error) {
        $okuError = $error->getMessage();
        http_response_code($error->getCode() ?: 422);
    }
}

$submission = oku_current_submission(me()['id']);
$okuStatus = $submission['status'] ?? 'not_submitted';
head('OKU Parking Verification');
?>
<div class="oku-verification-page">
  <p><a class="vehicle-secondary-link" href="<?= e($profileUrl) ?>"><?= te('Back to profile') ?></a></p>
  <section class="card oku-verification-card" id="oku-parking-verification" aria-labelledby="oku-verification-heading">
    <img class="oku-verification-logo" src="assets/images/oku-parking.png" width="80" height="80" alt="<?= te('OKU parking symbol') ?>">
    <h1 id="oku-verification-heading"><?= te('OKU Parking Verification') ?></h1>
    <p class="oku-verification-status"><?= te('Status') ?> <strong><?= te(OKU_STATUS_LABELS[$okuStatus]) ?></strong></p>
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
      <form method="post" enctype="multipart/form-data" action="<?= e($verificationPage) ?>">
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
      <p><a class="vehicle-secondary-link" href="<?= e($parkingUrl) ?>"><?= te('Back to parking') ?></a></p>
    <?php endif; ?>
  </section>
</div>
<?php foot(showChatbot: false); ?>
