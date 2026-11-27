<?php
require __DIR__ . '/includes/demo-session.php';
start_demo_session();

// Signing out also works when MariaDB is offline.
clear_demo_login();
unset(
    $_SESSION['flash'], $_SESSION['failures'],
    $_SESSION['demo_chat'], $_SESSION['parking_reviews'], $_SESSION['paypal_checkout'], $_SESSION['wallet_topup_invalid_amount'],
    $_SESSION['registration_pending'], $_SESSION['registration_link_error'], $_SESSION['password_reset_pending'],
    $_SESSION['email_change_pending'], $_SESSION['email_change_error']
);
session_regenerate_id(true);
session_write_close();
header('Location: pages/login.php');
exit;
