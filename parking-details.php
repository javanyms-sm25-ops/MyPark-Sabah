<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_customer_wallet();

require_once __DIR__ . '/includes/parking-booking.php';

$userId = me()['id'];
$customer = me()['role'] === 'user';
$vehicleStorageError = '';
$vehicleState = ['vehicles' => [], 'default_id' => null, 'selected_id' => null];
if ($customer) {
    try {
        $vehicleState = saved_vehicle_state($userId);
    } catch (RuntimeException $error) {
        $vehicleStorageError = $error->getMessage();
        http_response_code(503);
    }
}
$vehicles = $vehicleState['vehicles'];
$defaultVehicle = $vehicleState['default_id'];
$selectedVehicle = $vehicleState['selected_id'];
$resume = parking_resume_fields($_GET);
$requestedVehicle = $_GET['vehicle_id'] ?? '';
$draft = [
    'lot_id' => (string)($resume['lot_id'] ?? ''),
    'bay_type' => $resume['bay_type'] ?? 'regular',
    'hours' => (string)($resume['hours'] ?? 1),
    'vehicle_id' => $customer ? ($selectedVehicle ?? '') : 'manual',
    'plate' => '',
    'save_vehicle' => false,
];
if (is_string($requestedVehicle) && isset($vehicles[$requestedVehicle])) {
    $draft['vehicle_id'] = $requestedVehicle;
}
header('Cache-Control: private, no-store');

$errors = [];
$bookingError = $vehicleStorageError;
$review = null;
$addVehicleError = '';
$pending = $_SESSION['parking_reviews'][$userId] ?? null;
$pendingReview = $pending['review'] ?? null;
// Old sessions stored the review metadata directly beside the draft.
$confirmation = $pendingReview ?? $pending;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if ($vehicleStorageError !== '') throw new RuntimeException($vehicleStorageError, 503);
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
                change_saved_vehicle($userId, ['vehicle_action' => 'select', 'vehicle_id' => $added['id']]);
                flash($added['message']);
                header('Location: parking-details.php?' . http_build_query($resume + ['vehicle_id' => $added['id']]), true, 303);
                exit;
            } catch (InvalidArgumentException | RuntimeException $error) {
                $addVehicleError = $error->getMessage();
                http_response_code($error->getCode() ?: 422);
            }
        } elseif ($action === 'review_parking') {
            unset($_SESSION['parking_reviews'][$userId]);
            $draft = parking_draft_from_post($_POST);
            $checked = validate_parking_draft(me(), $draft);
            $errors = $checked['errors'];
            if ($errors) {
                http_response_code(422);
            } else {
                if ($customer) change_saved_vehicle($userId, ['vehicle_action' => 'select', 'vehicle_id' => $draft['vehicle_id']]);
                $_SESSION['parking_reviews'][$userId] = [
                    'draft' => $draft,
                    'review' => $checked['details'] + ['token' => bin2hex(random_bytes(32)), 'reviewed_at' => time()],
                ];
                header('Location: parking-details.php?step=review#parkingReview', true, 303);
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
                if ((isset($_POST['bay_type']) && $_POST['bay_type'] !== ($draft['bay_type'] ?? 'regular'))
                    || (isset($_POST['vehicle_id']) && $_POST['vehicle_id'] !== $draft['vehicle_id'])) {
                    http_response_code(422);
                    $bookingError = 'Review your changes before starting parking.';
                } elseif ($action === 'edit_parking') {
                    $_SESSION['parking_reviews'][$userId] = ['draft' => $draft];
                    header('Location: parking-details.php?step=edit#bookingPanel', true, 303);
                    exit;
                } elseif (($_POST['payment_method'] ?? '') !== 'wallet') {
                    http_response_code(409);
                    $bookingError = 'Review the parking fee before confirming payment.';
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
                                try {
                                    $bookingId = start_parking(me(), $details);
                                    if ($details['fee'] <= 0) flash('Parking session created. Your e-ticket is ready.');
                                    header('Location: ticket.php?id=' . $bookingId, true, 303);
                                    exit;
                                } catch (RuntimeException $error) {
                                    $bookingError = $error->getMessage();
                                }
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
    } catch (InvalidArgumentException | RuntimeException $error) {
        $bookingError = $error->getMessage();
        http_response_code($error->getCode() ?: 503);
    }
} elseif (($_GET['step'] ?? '') === 'edit' && isset($pending['draft']) && !isset($confirmation['token'])) {
    $draft = $pending['draft'];
} elseif (($_GET['step'] ?? '') === 'review' && $vehicleStorageError === '') {
    if ($pendingReview === null) {
        http_response_code(409);
        $bookingError = 'This review is no longer valid. Check your parking details again.';
    } elseif (time() - $pendingReview['reviewed_at'] >= 600) {
        unset($_SESSION['parking_reviews'][$userId]);
        http_response_code(409);
        $draft = $pending['draft'];
        $bookingError = 'This review has expired. Review your parking details again.';
    } else {
        $draft = $pending['draft'];
        $checked = validate_parking_draft(me(), $draft);
        $errors = $checked['errors'];
        if ($errors || $checked['details']['plate'] !== $pendingReview['plate'] || $checked['details']['fee'] !== $pendingReview['fee']) {
            unset($_SESSION['parking_reviews'][$userId]);
            http_response_code(409);
            $bookingError = 'The fee or vehicle details changed. Review the updated details before starting.';
        } else {
            $review = $pendingReview;
        }
    }
}

$lots = active_parking_locations();
head('Parking details');
?>

<?php require __DIR__ . '/includes/booking-panel.php'; ?>

<?php foot(); ?>
