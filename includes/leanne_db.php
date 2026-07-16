<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'car_rental_management';
$db_port = 3306;
$db_connection_error = '';

$conn = mysqli_init();

if ($conn === false) {
    $db_connection_error = 'Database connection could not be initialized.';

    if (defined('ALLOW_DB_FAILURE') && ALLOW_DB_FAILURE) {
        $conn = null;
        return;
    }

    exit('Database connection could not be initialized.');
}

mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

$connected = mysqli_real_connect(
    $conn,
    $db_host,
    $db_user,
    $db_password,
    $db_name,
    $db_port
);

if (!$connected) {
    $db_connection_error =
        'Database connection failed. Please check the database name and XAMPP MySQL.';

    error_log(
        'Database connection failed: ' . mysqli_connect_error()
    );

    if (defined('ALLOW_DB_FAILURE') && ALLOW_DB_FAILURE) {
        $conn = null;
        return;
    }

    exit($db_connection_error);
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log(
        'Unable to set database charset: ' . mysqli_error($conn)
    );
}

// Yana:
// ready na yung shared $conn para sa login, register,
// email verification, at manage users page mo.

// Jeiven:
// same $conn gamitin mo sa vehicles, dashboard, at reports.

// Faith:
// same $conn din gamitin mo sa checkout at payment.
