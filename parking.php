<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if (me()['role'] === 'superadmin') {
    header('Location: admin/', true, 303);
    exit;
}

require_once __DIR__ . '/includes/parking-booking.php';

$userId = me()['id'];
$customer = me()['role'] === 'user';
$vehicles = $customer ? saved_vehicles($userId) : [];
$defaultVehicle = $customer ? default_vehicle_id($userId) : null;
$resume = parking_resume_fields($_GET);
$requestedVehicle = $_GET['vehicle_id'] ?? '';
$draft = [
    'lot_id' => (string)($resume['lot_id'] ?? ''),
    'bay_type' => 'regular',
    'hours' => (string)($resume['hours'] ?? 1),
    'vehicle_id' => $vehicles ? ($defaultVehicle ?? '') : 'manual',
    'plate' => '',
    'save_vehicle' => false,
];
if (is_string($requestedVehicle) && isset($vehicles[$requestedVehicle])) {
    $draft['vehicle_id'] = $requestedVehicle;
}

$errors = [];
$bookingError = '';
$review = null;
$addVehicleError = '';
$pending = $_SESSION['parking_reviews'][$userId] ?? null;
$pendingReview = $pending['review'] ?? null;
// Old sessions stored the review metadata directly beside the draft.
$confirmation = $pendingReview ?? $pending;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_booking_vehicle') {
        if (!$customer) {
            http_response_code(403);
            exit('Vehicle management is available to customers.');
        }
        $resume = parking_resume_fields($_POST);
        foreach ($resume as $key => $value) $draft[$key] = (string)$value;
        try {
            $added = change_saved_vehicle($userId, ['vehicle_action' => 'add'] + $_POST);
            flash($added['message']);
            header('Location: parking.php?' . http_build_query($resume + ['vehicle_id' => $added['id']]), true, 303);
            exit;
        } catch (InvalidArgumentException $error) {
            $addVehicleError = $error->getMessage();
            http_response_code(422);
        }
    } elseif ($action === 'review_parking') {
        unset($_SESSION['parking_reviews'][$userId]);
        $draft = parking_draft_from_post($_POST);
        $checked = validate_parking_draft(me(), $draft);
        $errors = $checked['errors'];
        if ($errors) {
            http_response_code(422);
        } else {
            $_SESSION['parking_reviews'][$userId] = [
                'draft' => $draft,
                'review' => $checked['details'] + ['token' => bin2hex(random_bytes(32)), 'reviewed_at' => time()],
            ];
            header('Location: parking.php?step=review#parkingReview', true, 303);
            exit;
        }
    } elseif ($action === 'start_parking' || $action === 'edit_parking') {
        if (!isset($confirmation['token']) || !hash_equals($confirmation['token'], $_POST['review_token'] ?? '')) {
            http_response_code(409);
            $bookingError = 'This review is no longer valid. Check your parking details again.';
        } else {
            // PHP holds the session lock here, so a second submission cannot use the same review.
            unset($_SESSION['parking_reviews'][$userId]);
            $draft = $pending['draft'];
            if (isset($_POST['bay_type']) && $_POST['bay_type'] !== ($draft['bay_type'] ?? 'regular')) {
                http_response_code(422);
                $bookingError = 'Review your changes before starting parking.';
            } elseif ($action === 'edit_parking') {
                $_SESSION['parking_reviews'][$userId] = ['draft' => $draft];
                header('Location: parking.php?step=edit#bookingPanel', true, 303);
                exit;
            } else {
                if (time() - $confirmation['reviewed_at'] >= 600) {
                    $bookingError = 'This review has expired. Review your parking details again.';
                } else {
                    $checked = validate_parking_draft(me(), $draft);
                    $errors = $checked['errors'];
                    $details = $checked['details'];
                    if (!$errors) {
                        if ($details['fee'] !== $confirmation['fee'] || $details['plate'] !== $confirmation['plate']) {
                            $bookingError = 'The fee or vehicle details changed. Review the updated details before starting.';
                        } else {
                            $bookingId = start_parking(me(), $details);
                            flash('Parking session created. Your e-ticket is ready.');
                            header('Location: ticket.php?id=' . $bookingId, true, 303);
                            exit;
                        }
                    }
                }
                http_response_code(409);
            }
        }
    } else {
        http_response_code(400);
        $bookingError = 'Review your parking details before starting.';
    }
} elseif (($_GET['step'] ?? '') === 'edit' && isset($pending['draft']) && !isset($confirmation['token'])) {
    $draft = $pending['draft'];
} elseif (($_GET['step'] ?? '') === 'review') {
    if ($pendingReview === null) {
        http_response_code(409);
        $bookingError = 'This review is no longer valid. Check your parking details again.';
    } elseif (time() - $pendingReview['reviewed_at'] >= 600) {
        unset($_SESSION['parking_reviews'][$userId]);
        http_response_code(409);
        $draft = $pending['draft'];
        $bookingError = 'This review has expired. Review your parking details again.';
    } else {
        $review = $pendingReview;
        $draft = $pending['draft'];
    }
}

$lots = $_SESSION['demo_lots'];
head('Parking details');
?>

<?php require __DIR__ . '/includes/booking-panel.php'; ?>

<?php foot(); ?>
