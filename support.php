<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$_SESSION['demo_chat'] ??= [];
MyParkData::$records['demo_reviews'] ??= [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'review') {
        $rating = filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_INT);
        $review = trim($_POST['review'] ?? '');
        if ($rating === false || $rating < 1 || $rating > 5 || $review === '' || strlen($review) > 2000) {
            flash('Review failed. Choose a rating and enter a review of up to 2,000 characters.');
        } else {
            MyParkData::$records['demo_reviews'][] = ['user_id' => me()['id'], 'rating' => $rating, 'review' => $review, 'at' => date('Y-m-d H:i')];
            flash('Review saved.');
        }
    } else {
        $message = trim($_POST['message'] ?? '');
        if ($message === '' || strlen($message) > 2000) {
            flash('Message failed. Enter a message of up to 2,000 characters.');
        } else {
            $_SESSION['demo_chat'][] = ['by' => me()['name'], 'text' => $message, 'at' => date('H:i')];
            $_SESSION['demo_chat'][] = ['by' => 'MyPark Support (demo)', 'text' => 'Your message is saved in this demo session. Live agent support is not connected yet.', 'at' => date('H:i')];
        }
    }
    header('Location: support.php');
    exit;
}
head('Support');
?>
<h1><?= te('Parking support') ?></h1>
<p class="muted"><?= te('Prototype chat. Live agent connection can be enabled in the final backend.') ?></p>
<section class="card chat" id="chat">
  <?php foreach ($_SESSION['demo_chat'] as $message): ?>
    <p><b><?= e($message['by']) ?></b> <small><?= e($message['at']) ?></small><br><?= e($message['text']) ?></p>
  <?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label><?= te('Message') ?><input name="message" required maxlength="2000" placeholder="<?= te('How can we help?') ?>"></label><button><?= te('Send') ?></button>
  </form>
</section>
<h2><?= te('Rate your parking experience') ?></h2>
<form method="post" class="card">
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="review">
  <label><?= te('Rating') ?><select name="rating">
    <?php foreach ([5 => 'Excellent', 4 => 'Good', 3 => 'Okay', 2 => 'Poor', 1 => 'Bad'] as $value => $label): ?>
      <option value="<?= $value ?>"><?= $value ?> - <?= te($label) ?></option>
    <?php endforeach; ?>
  </select></label>
  <label><?= te('Review') ?><input name="review" placeholder="<?= te('Share feedback') ?>" maxlength="2000" required></label><button><?= te('Submit review') ?></button>
</form>
<?php $reviews = array_filter(MyParkData::$records['demo_reviews'], fn($review) => $review['user_id'] === me()['id']); ?>
<?php if ($reviews): ?>
  <h2><?= te('Your reviews') ?></h2>
  <?php foreach (array_reverse($reviews) as $review): ?>
    <article class="card"><b><?= (int)$review['rating'] ?>/5</b><small> · <?= e($review['at']) ?></small><p><?= e($review['review']) ?></p></article>
  <?php endforeach; ?>
<?php endif; ?>
<?php foot(); ?>
