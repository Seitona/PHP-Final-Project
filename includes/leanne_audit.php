<?php
declare(strict_types=1);

// Leanne:
// reusable audit function para pare-pareho logging natin.
function log_audit(
    mysqli $conn,
    string $action,
    string $details = '',
    ?int $user_id = null
): bool {
    $action = trim($action);
    $details = trim($details);

    if ($action === '') {
        return false;
    }

    $action = substr($action, 0, 100);
    $details = substr($details, 0, 1000);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $sql = 'INSERT INTO audit_logs
            (user_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())';

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        error_log('Audit prepare error: ' . mysqli_error($conn));
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isss',
        $user_id,
        $action,
        $details,
        $ip_address
    );

    $saved = mysqli_stmt_execute($stmt);

    if (!$saved) {
        error_log('Audit save error: ' . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    return $saved;
}