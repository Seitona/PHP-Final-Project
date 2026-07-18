<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Please log in to continue to payment.';
    header('Location: yana_login.php');
    exit;
}

$booking_id = (int) ($_SESSION['booking_id'] ?? 0);
if ($booking_id < 1) {
    $_SESSION['message'] = 'No booking is ready for payment.';
    header('Location: leanne_cart.php');
    exit;
}

require_once __DIR__ . '/includes/leanne_db.php';
require_once __DIR__ . '/includes/leanne_audit.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];
$errors = [];
$allowed_methods = ['Cash', 'GCash', 'Card'];
$payment_method = (string) ($_POST['payment_method'] ?? 'Cash');

$booking_sql = "SELECT b.id, b.user_id, b.vehicle_id, b.start_date, b.end_date,
                       b.rental_days, b.daily_rate, b.total_amount, b.status,
                       v.brand, v.model, v.year, v.category, v.image_path,
                       v.availability_status
                FROM bookings b
                INNER JOIN vehicles v ON v.id = b.vehicle_id
                WHERE b.id = ? AND b.user_id = ?
                LIMIT 1";
$booking_stmt = mysqli_prepare($conn, $booking_sql);
$booking = null;
if ($booking_stmt) {
    mysqli_stmt_bind_param($booking_stmt, 'ii', $booking_id, $user_id);
    mysqli_stmt_execute($booking_stmt);
    $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($booking_stmt));
    mysqli_stmt_close($booking_stmt);
}

if (!$booking) {
    unset($_SESSION['booking_id']);
    $_SESSION['message'] = 'The booking could not be found.';
    header('Location: leanne_cart.php');
    exit;
}

if ($booking['status'] !== 'Pending') {
    $errors[] = 'This booking is no longer awaiting payment.';
}
if ($booking['availability_status'] !== 'Available') {
    $errors[] = 'This vehicle is no longer available for reservation.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $posted_token)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }
    if (!in_array($payment_method, $allowed_methods, true)) {
        $errors[] = 'Please choose a valid payment method.';
    }

    if (!$errors) {
        mysqli_begin_transaction($conn);
        $saved = false;
        $lock_booking_sql = 'SELECT id, vehicle_id, total_amount, status FROM bookings WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE';
        $lock_booking_stmt = mysqli_prepare($conn, $lock_booking_sql);
        $locked_booking = null;

        if ($lock_booking_stmt) {
            mysqli_stmt_bind_param($lock_booking_stmt, 'ii', $booking_id, $user_id);
            mysqli_stmt_execute($lock_booking_stmt);
            $locked_booking = mysqli_fetch_assoc(mysqli_stmt_get_result($lock_booking_stmt));
            mysqli_stmt_close($lock_booking_stmt);
        }

        if (!$locked_booking || $locked_booking['status'] !== 'Pending') {
            $errors[] = 'This booking is no longer awaiting payment.';
        } else {
            $locked_vehicle_id = (int) $locked_booking['vehicle_id'];
            $vehicle_lock_stmt = mysqli_prepare($conn, 'SELECT availability_status FROM vehicles WHERE id = ? LIMIT 1 FOR UPDATE');
            $locked_vehicle = null;
            if ($vehicle_lock_stmt) {
                mysqli_stmt_bind_param($vehicle_lock_stmt, 'i', $locked_vehicle_id);
                mysqli_stmt_execute($vehicle_lock_stmt);
                $locked_vehicle = mysqli_fetch_assoc(mysqli_stmt_get_result($vehicle_lock_stmt));
                mysqli_stmt_close($vehicle_lock_stmt);
            }

            if (!$locked_vehicle || $locked_vehicle['availability_status'] !== 'Available') {
                $errors[] = 'This vehicle is no longer available for reservation.';
            } else {
                $paid_check_stmt = mysqli_prepare($conn, "SELECT id FROM payments WHERE booking_id = ? AND payment_status = 'Paid' LIMIT 1");
                $already_paid = null;
                if ($paid_check_stmt) {
                    mysqli_stmt_bind_param($paid_check_stmt, 'i', $booking_id);
                    mysqli_stmt_execute($paid_check_stmt);
                    $already_paid = mysqli_fetch_assoc(mysqli_stmt_get_result($paid_check_stmt));
                    mysqli_stmt_close($paid_check_stmt);
                } else {
                    $errors[] = 'Payment records could not be checked.';
                }

                if ($already_paid) {
                    $errors[] = 'This booking has already been paid.';
                } elseif (!$errors) {
                    $amount = (float) $locked_booking['total_amount'];
                    $reference_number = 'P4U-' . $booking_id . '-' . strtoupper(bin2hex(random_bytes(4)));
                    $payment_status = 'Paid';
                    $payment_sql = 'INSERT INTO payments
                        (booking_id, amount, payment_method, payment_status,
                         reference_number, paid_at, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())';
                    $payment_stmt = mysqli_prepare($conn, $payment_sql);

                    if ($payment_stmt) {
                        mysqli_stmt_bind_param($payment_stmt, 'idsss', $booking_id, $amount, $payment_method, $payment_status, $reference_number);
                        $payment_saved = mysqli_stmt_execute($payment_stmt);
                        mysqli_stmt_close($payment_stmt);

                        if ($payment_saved) {
                            $booking_update = mysqli_prepare($conn, "UPDATE bookings SET status = 'Reserved', updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                            $booking_updated = false;
                            if ($booking_update) {
                                mysqli_stmt_bind_param($booking_update, 'i', $booking_id);
                                mysqli_stmt_execute($booking_update);
                                $booking_updated = mysqli_stmt_affected_rows($booking_update) === 1;
                                mysqli_stmt_close($booking_update);
                            }

                            $vehicle_update = mysqli_prepare($conn, "UPDATE vehicles SET availability_status = 'Reserved', updated_at = NOW() WHERE id = ? AND availability_status = 'Available'");
                            $vehicle_updated = false;
                            if ($vehicle_update) {
                                mysqli_stmt_bind_param($vehicle_update, 'i', $locked_vehicle_id);
                                mysqli_stmt_execute($vehicle_update);
                                $vehicle_updated = mysqli_stmt_affected_rows($vehicle_update) === 1;
                                mysqli_stmt_close($vehicle_update);
                            }
                            $saved = $booking_updated && $vehicle_updated;
                        }
                    }
                }
            }
        }

        if ($saved && mysqli_commit($conn)) {
            log_audit($conn, 'PAYMENT_COMPLETED', 'Booking #' . $booking_id . ' was paid and reserved.', $user_id);
            unset($_SESSION['rental_cart'], $_SESSION['booking_id']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['message'] = 'Payment successful. Your vehicle is now reserved. Reference: ' . $reference_number;
            header('Location: leanne_cart.php');
            exit;
        }

        mysqli_rollback($conn);
        if (!$errors) {
            $errors[] = 'Payment could not be completed. No charge or reservation was recorded.';
        }
    }
}

$page_title = 'Payment | Party4U';
$active_page = '';
require __DIR__ . '/includes/faith_header.php';
?>
<main class="container py-5">
    <div class="mb-4">
        <h1 class="h2 mb-1">Payment</h1>
        <p class="text-secondary mb-0">Choose a payment method to finish your reservation.</p>
    </div>

    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3"><strong>Payment method</strong></div>
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <div class="vstack gap-3 mb-4">
                            <?php foreach ($allowed_methods as $method): ?>
                                <label class="border rounded p-3 d-flex align-items-center gap-3">
                                    <input class="form-check-input mt-0" type="radio" name="payment_method" value="<?php echo e($method); ?>" <?php echo $payment_method === $method ? 'checked' : ''; ?> required>
                                    <span><strong><?php echo e($method); ?></strong><small class="text-secondary d-block"><?php echo $method === 'Cash' ? 'Record a cash payment for this reservation.' : 'Confirm your ' . e($method) . ' payment.'; ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-success btn-lg w-100" type="submit" <?php echo $errors ? 'disabled' : ''; ?>>Pay PHP <?php echo number_format((float) $booking['total_amount'], 2); ?></button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3"><strong>Booking summary</strong></div>
                <div class="card-body">
                    <div class="d-flex gap-3 mb-3">
                        <?php if (!empty($booking['image_path'])): ?><img class="vehicle-thumb rounded" src="<?php echo e($booking['image_path']); ?>" alt="<?php echo e($booking['brand'] . ' ' . $booking['model']); ?>"><?php endif; ?>
                        <div><h2 class="h5 mb-1"><?php echo e($booking['brand'] . ' ' . $booking['model']); ?></h2><span class="text-secondary"><?php echo e((string) $booking['year']); ?> &middot; <?php echo e($booking['category']); ?></span></div>
                    </div>
                    <div class="d-flex justify-content-between mb-2"><span>Booking</span><strong>#<?php echo (int) $booking['id']; ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Pickup</span><strong><?php echo e(date('M j, Y', strtotime($booking['start_date']))); ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Return</span><strong><?php echo e(date('M j, Y', strtotime($booking['end_date']))); ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Rental days</span><strong><?php echo (int) $booking['rental_days']; ?></strong></div>
                    <div class="d-flex justify-content-between mb-3"><span>Daily rate</span><strong>PHP <?php echo number_format((float) $booking['daily_rate'], 2); ?></strong></div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5"><strong>Amount due</strong><strong>PHP <?php echo number_format((float) $booking['total_amount'], 2); ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/faith_footer.php'; ?>
