<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/includes/leanne_db.php';
require_once __DIR__ . '/includes/leanne_audit.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function valid_date(string $date): bool
{
    $parsed_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsed_date !== false
        && $parsed_date->format('Y-m-d') === $date;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$cart = $_SESSION['rental_cart'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = 'Your session expired. Please refresh the page.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'remove') {
            if ($cart) {
                log_audit(
                    $conn,
                    'REMOVE_FROM_CART',
                    'A vehicle was removed from the rental cart.',
                    isset($_SESSION['user_id'])
                        ? (int) $_SESSION['user_id']
                        : null
                );
            }

            unset($_SESSION['rental_cart']);

            $_SESSION['message'] =
                'Vehicle removed from your rental cart.';

            header('Location: leanne_cart.php');
            exit;
        }

        if ($action === 'update' && $cart) {
            $start_date = trim(
                (string) ($_POST['start_date'] ?? '')
            );

            $end_date = trim(
                (string) ($_POST['end_date'] ?? '')
            );

            if (!valid_date($start_date) || !valid_date($end_date)) {
                $errors[] = 'Please choose valid rental dates.';
            } elseif ($start_date < date('Y-m-d')) {
                $errors[] = 'The pickup date cannot be in the past.';
            } elseif ($end_date <= $start_date) {
                $errors[] =
                    'The return date must be after the pickup date.';
            }

            if (!$errors) {
                $start = new DateTimeImmutable($start_date);
                $end = new DateTimeImmutable($end_date);
                $rental_days = (int) $start->diff($end)->days;

                if ($rental_days < 1 || $rental_days > 365) {
                    $errors[] =
                        'The rental period must be between 1 and 365 days.';
                }
            }

            if (!$errors) {
                $cart['start_date'] = $start_date;
                $cart['end_date'] = $end_date;
                $cart['rental_days'] = $rental_days;
                $cart['total_amount'] =
                    (float) $cart['daily_rate'] * $rental_days;

                $_SESSION['rental_cart'] = $cart;
                $_SESSION['message'] = 'Rental dates updated.';

                header('Location: leanne_cart.php');
                exit;
            }
        }
    }
}

$vehicle = null;

if ($cart) {
    $vehicle_id = (int) ($cart['vehicle_id'] ?? 0);

    $sql = "SELECT id, brand, model, year, category, transmission,
                   fuel_type, seating_capacity, plate_number, color,
                   daily_rate, image_path, availability_status
            FROM vehicles
            WHERE id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $vehicle = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);
    }

    if (!$vehicle) {
        unset($_SESSION['rental_cart']);
        $cart = null;
        $errors[] = 'The selected vehicle could not be found.';
    } elseif ($vehicle['availability_status'] !== 'Available') {
        $errors[] = 'The selected vehicle is no longer available.';
    } else {
        // Leanne:
        // current database price lagi gamitin para safe ang total.
        $cart['daily_rate'] = (float) $vehicle['daily_rate'];

        $cart['total_amount'] =
            $cart['daily_rate'] * (int) $cart['rental_days'];

        $_SESSION['rental_cart'] = $cart;
    }
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rental Cart | Car Rental</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f6f9;
        }

        .cart-image {
            width: 180px;
            height: 125px;
            object-fit: cover;
            background: #e9ecef;
        }

        .cart-placeholder {
            width: 180px;
            height: 125px;
            display: grid;
            place-items: center;
            background: #e9ecef;
            color: #6c757d;
        }

        @media (max-width: 576px) {
            .cart-image,
            .cart-placeholder {
                width: 100%;
                height: 200px;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="leanne_store.php">
            Car Rental
        </a>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="leanne_store.php">
                Browse Cars
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a class="btn btn-danger btn-sm" href="yana_logout.php">
                    Logout
                </a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="yana_login.php">
                    Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="mb-4">
        <h1 class="h2 mb-1">Rental Cart</h1>

        <p class="text-secondary mb-0">
            Review your selected vehicle and rental dates.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-info">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$cart || !$vehicle): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <h2 class="h4">Your rental cart is empty</h2>

                <p class="text-secondary">
                    Choose an available vehicle to begin your reservation.
                </p>

                <a class="btn btn-primary" href="leanne_store.php">
                    Browse Available Cars
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <?php if (!empty($vehicle['image_path'])): ?>
                                <img
                                    class="cart-image rounded"
                                    src="<?php echo e(
                                        $vehicle['image_path']
                                    ); ?>"
                                    alt="<?php echo e(
                                        $vehicle['brand']
                                        . ' '
                                        . $vehicle['model']
                                    ); ?>"
                                >
                            <?php else: ?>
                                <div class="cart-placeholder rounded">
                                    No image
                                </div>
                            <?php endif; ?>

                            <div class="flex-grow-1">
                                <span class="badge text-bg-success">
                                    <?php echo e(
                                        $vehicle['availability_status']
                                    ); ?>
                                </span>

                                <h2 class="h4 mt-2 mb-1">
                                    <?php echo e(
                                        $vehicle['brand']
                                        . ' '
                                        . $vehicle['model']
                                    ); ?>
                                </h2>

                                <p class="text-secondary">
                                    <?php echo e(
                                        (string) $vehicle['year']
                                    ); ?>
                                    ·
                                    <?php echo e($vehicle['category']); ?>
                                    ·
                                    <?php echo e(
                                        $vehicle['transmission']
                                    ); ?>
                                </p>

                                <strong>
                                    ₱<?php echo number_format(
                                        (float) $cart['daily_rate'],
                                        2
                                    ); ?>
                                </strong>

                                <span class="text-secondary">per day</span>
                            </div>
                        </div>

                        <hr>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e(
                                    $_SESSION['csrf_token']
                                ); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="update"
                            >

                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label
                                        for="start_date"
                                        class="form-label"
                                    >
                                        Pickup Date
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="start_date"
                                        name="start_date"
                                        min="<?php echo date('Y-m-d'); ?>"
                                        value="<?php echo e(
                                            $cart['start_date']
                                        ); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-5">
                                    <label
                                        for="end_date"
                                        class="form-label"
                                    >
                                        Return Date
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="end_date"
                                        name="end_date"
                                        min="<?php
                                            echo date(
                                                'Y-m-d',
                                                strtotime('+1 day')
                                            );
                                        ?>"
                                        value="<?php echo e(
                                            $cart['end_date']
                                        ); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-2 d-grid">
                                    <button
                                        class="btn btn-outline-primary"
                                        type="submit"
                                    >
                                        Update
                                    </button>
                                </div>
                            </div>
                        </form>

                        <form method="post" class="mt-3">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e(
                                    $_SESSION['csrf_token']
                                ); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="remove"
                            >

                            <button
                                class="btn btn-sm btn-outline-danger"
                                type="submit"
                                onclick="return confirm(
                                    'Remove this vehicle from your cart?'
                                )"
                            >
                                Remove Vehicle
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <strong>Rental Summary</strong>
                    </div>

                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Pickup</span>

                            <strong>
                                <?php echo e(
                                    date(
                                        'M j, Y',
                                        strtotime($cart['start_date'])
                                    )
                                ); ?>
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Return</span>

                            <strong>
                                <?php echo e(
                                    date(
                                        'M j, Y',
                                        strtotime($cart['end_date'])
                                    )
                                ); ?>
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Rental days</span>

                            <strong>
                                <?php echo (int) $cart['rental_days']; ?>
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span>Daily rate</span>

                            <strong>
                                ₱<?php echo number_format(
                                    (float) $cart['daily_rate'],
                                    2
                                ); ?>
                            </strong>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between fs-5 mb-4">
                            <strong>Total</strong>

                            <strong>
                                ₱<?php echo number_format(
                                    (float) $cart['total_amount'],
                                    2
                                ); ?>
                            </strong>
                        </div>

                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a
                                class="btn btn-primary w-100"
                                href="yana_login.php"
                            >
                                Login to Continue
                            </a>
                        <?php elseif (
                            $vehicle['availability_status'] !== 'Available'
                        ): ?>
                            <button
                                class="btn btn-secondary w-100"
                                disabled
                            >
                                Vehicle Unavailable
                            </button>
                        <?php else: ?>
                            <!-- Faith:
                            dito mo nalang kunin yung
                            $_SESSION['rental_cart'] para sa
                            checkout at payment mo. -->

                            <a
                                class="btn btn-success w-100"
                                href="faith_checkout.php"
                            >
                                Continue to Checkout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
</body>
</html>