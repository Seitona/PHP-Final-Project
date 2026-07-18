<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Please log in to continue to checkout.';
    header('Location: yana_login.php');
    exit;
}

if (empty($_SESSION['rental_cart']) || !is_array($_SESSION['rental_cart'])) {
    $_SESSION['message'] = 'Your rental cart is empty. Please choose a vehicle first.';
    header('Location: leanne_cart.php');
    exit;
}

require_once __DIR__ . '/includes/leanne_db.php';
require_once __DIR__ . '/includes/leanne_audit.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function checkout_valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$cart = $_SESSION['rental_cart'];
$vehicle_id = (int) ($cart['vehicle_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$vehicle = null;
$customer = null;

$user_stmt = mysqli_prepare($conn, 'SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1');
if ($user_stmt) {
    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
    mysqli_stmt_close($user_stmt);
}

$vehicle_sql = "SELECT id, brand, model, year, category, transmission,
                       fuel_type, seating_capacity, plate_number, color,
                       daily_rate, image_path, availability_status
                FROM vehicles WHERE id = ? LIMIT 1";
$vehicle_stmt = mysqli_prepare($conn, $vehicle_sql);
if ($vehicle_stmt) {
    mysqli_stmt_bind_param($vehicle_stmt, 'i', $vehicle_id);
    mysqli_stmt_execute($vehicle_stmt);
    $vehicle = mysqli_fetch_assoc(mysqli_stmt_get_result($vehicle_stmt));
    mysqli_stmt_close($vehicle_stmt);
}

if (!$customer) {
    $errors[] = 'Your customer account could not be loaded.';
}
if (!$vehicle) {
    $errors[] = 'The selected vehicle could not be found.';
} elseif ($vehicle['availability_status'] !== 'Available') {
    $errors[] = 'The selected vehicle is no longer available.';
}

$start_date = (string) ($cart['start_date'] ?? '');
$end_date = (string) ($cart['end_date'] ?? '');
$rental_days = 0;

if (!checkout_valid_date($start_date) || !checkout_valid_date($end_date)) {
    $errors[] = 'The rental dates in your cart are invalid.';
} elseif ($start_date < date('Y-m-d') || $end_date <= $start_date) {
    $errors[] = 'Please return to your cart and choose valid future rental dates.';
} else {
    $rental_days = (int) (new DateTimeImmutable($start_date))->diff(new DateTimeImmutable($end_date))->days;
    if ($rental_days < 1 || $rental_days > 365) {
        $errors[] = 'The rental period must be between 1 and 365 days.';
    }
}

$daily_rate = $vehicle ? (float) $vehicle['daily_rate'] : 0.0;
$total_amount = $daily_rate * $rental_days;
$cart['rental_days'] = $rental_days;
$cart['daily_rate'] = $daily_rate;
$cart['total_amount'] = $total_amount;
$_SESSION['rental_cart'] = $cart;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $posted_token)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if (!$errors) {
        mysqli_begin_transaction($conn);
        $locked_stmt = mysqli_prepare($conn, $vehicle_sql . ' FOR UPDATE');
        $saved = false;

        if ($locked_stmt) {
            mysqli_stmt_bind_param($locked_stmt, 'i', $vehicle_id);
            mysqli_stmt_execute($locked_stmt);
            $locked_vehicle = mysqli_fetch_assoc(mysqli_stmt_get_result($locked_stmt));
            mysqli_stmt_close($locked_stmt);

            if (!$locked_vehicle || $locked_vehicle['availability_status'] !== 'Available') {
                $errors[] = 'This vehicle was just reserved by another customer.';
            } else {
                $daily_rate = (float) $locked_vehicle['daily_rate'];
                $total_amount = $daily_rate * $rental_days;
                $status = 'Pending';
                $insert_sql = 'INSERT INTO bookings
                    (user_id, vehicle_id, start_date, end_date, rental_days,
                     daily_rate, total_amount, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
                $insert_stmt = mysqli_prepare($conn, $insert_sql);

                if ($insert_stmt) {
                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        'iissidds',
                        $user_id,
                        $vehicle_id,
                        $start_date,
                        $end_date,
                        $rental_days,
                        $daily_rate,
                        $total_amount,
                        $status
                    );
                    $saved = mysqli_stmt_execute($insert_stmt);
                    if ($saved) {
                        $_SESSION['booking_id'] = mysqli_insert_id($conn);
                    }
                    mysqli_stmt_close($insert_stmt);
                }
            }
        }

        if ($saved && mysqli_commit($conn)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            log_audit($conn, 'BOOKING_CREATED', 'A pending booking was created at checkout.', $user_id);
            header('Location: faith_payment.php');
            exit;
        }

        mysqli_rollback($conn);
        if (!$errors) {
            $errors[] = 'We could not create your booking. Please try again.';
        }
    }
}

$page_title = 'Checkout | Party4U';
$active_page = '';
require __DIR__ . '/includes/faith_header.php';
?>
<main class="container py-5">
    <div class="mb-4">
        <h1 class="h2 mb-1">Checkout</h1>
        <p class="text-secondary mb-0">Review your details before creating the booking.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3"><strong>Customer details</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><small class="text-secondary d-block">Full name</small><strong><?php echo e($customer['full_name'] ?? (string) ($_SESSION['full_name'] ?? '')); ?></strong></div>
                        <div class="col-md-6"><small class="text-secondary d-block">Email</small><strong><?php echo e($customer['email'] ?? ''); ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3"><strong>Selected vehicle</strong></div>
                <div class="card-body">
                    <?php if ($vehicle): ?>
                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <?php if (!empty($vehicle['image_path'])): ?><img class="cart-image rounded" src="<?php echo e($vehicle['image_path']); ?>" alt="<?php echo e($vehicle['brand'] . ' ' . $vehicle['model']); ?>"><?php else: ?><div class="cart-placeholder rounded">No image</div><?php endif; ?>
                            <div>
                                <span class="badge text-bg-<?php echo $vehicle['availability_status'] === 'Available' ? 'success' : 'secondary'; ?>"><?php echo e($vehicle['availability_status']); ?></span>
                                <h2 class="h4 mt-2 mb-1"><?php echo e($vehicle['brand'] . ' ' . $vehicle['model']); ?></h2>
                                <p class="text-secondary mb-2"><?php echo e((string) $vehicle['year']); ?> &middot; <?php echo e($vehicle['category']); ?> &middot; <?php echo e($vehicle['transmission']); ?></p>
                                <span><?php echo e($vehicle['fuel_type']); ?> &middot; <?php echo (int) $vehicle['seating_capacity']; ?> seats &middot; <?php echo e($vehicle['color']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3"><strong>Rental summary</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Pickup</span><strong><?php echo checkout_valid_date($start_date) ? e(date('M j, Y', strtotime($start_date))) : '-'; ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Return</span><strong><?php echo checkout_valid_date($end_date) ? e(date('M j, Y', strtotime($end_date))) : '-'; ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Rental days</span><strong><?php echo $rental_days; ?></strong></div>
                    <div class="d-flex justify-content-between mb-3"><span>Daily rate</span><strong>PHP <?php echo number_format($daily_rate, 2); ?></strong></div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 mb-4"><strong>Total</strong><strong>PHP <?php echo number_format($total_amount, 2); ?></strong></div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <button class="btn btn-success btn-lg w-100" type="submit" <?php echo $errors ? 'disabled' : ''; ?>>Confirm and Continue</button>
                    </form>
                    <a class="btn btn-link w-100 mt-2" href="leanne_cart.php">Back to cart</a>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/faith_footer.php'; ?>
