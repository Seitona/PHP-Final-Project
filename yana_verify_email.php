<?php
session_start();

// Leanne: gamitin mo nalang yung shared $conn connection dito sa leanne_db.php
require_once __DIR__ . '/includes/leanne_db.php';

$code = $_GET['code'] ?? '';

if ($code === '' || !preg_match('/^[a-f0-9]{64}$/', $code)) {
    $_SESSION['message'] = 'Invalid verification link.';
} else {
    $sql = 'UPDATE users SET is_verified = 1, verification_code = NULL WHERE verification_code = ? AND is_verified = 0';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $code);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) === 1) {
        $_SESSION['message'] = 'Email verified successfully. You may now log in.';
    } else {
        $_SESSION['message'] = 'This verification link is invalid or was already used.';
    }

    mysqli_stmt_close($stmt);
}

header('Location: yana_login.php');
exit;