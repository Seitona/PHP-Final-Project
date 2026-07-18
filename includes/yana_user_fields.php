<?php
declare(strict_types=1);

function yana_ensure_user_contact_fields(mysqli $conn): void
{
    $sql = "SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME IN ('complete_address', 'contact_numbers')";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return;
    }

    $existing = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $existing[$row['COLUMN_NAME']] = true;
    }
    mysqli_free_result($result);

    $changes = [];
    if (!isset($existing['complete_address'])) {
        $changes[] = 'ADD COLUMN complete_address TEXT NULL AFTER email';
    }
    if (!isset($existing['contact_numbers'])) {
        $changes[] = 'ADD COLUMN contact_numbers VARCHAR(120) NULL AFTER complete_address';
    }

    if ($changes !== []) {
        mysqli_query($conn, 'ALTER TABLE users ' . implode(', ', $changes));
    }
}
