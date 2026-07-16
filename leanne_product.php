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

$vehicle_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$vehicle_id) {
    http_response_code(404);
    exit('Vehicle not found.');
}

$sql = "SELECT id, brand, model, year, category, transmission,
               fuel_type, seating_capacity, plate_number, color,
               daily_rate, description, image_path, availability_status
        FROM vehicles
        WHERE id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    exit('Vehicle information could not be loaded.');
}

mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$vehicle = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$vehicle) {
    http_response_code(404);
    exit('Vehicle not found.');
}

$errors = [];
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+1 day'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = 'Your session expired. Please refresh the page.';
    }

    $start_date = trim((string) ($_POST['start_date'] ?? ''));
    $end_date = trim((string) ($_POST['end_date'] ?? ''));

    if ($vehicle['availability_status'] !== 'Available') {
        $errors[] = 'This vehicle is no longer available.';
    }

    if (!valid_date($start_date) || !valid_date($end_date)) {
        $errors[] = 'Please choose valid rental dates.';
    } elseif ($start_date < date('Y-m-d')) {
        $errors[] = 'The pickup date cannot be in the past.';
    } elseif ($end_date <= $start_date) {
        $errors[] = 'The return date must be after the pickup date.';
    }

    if (!$errors) {
        $start = new DateTimeImmutable($start_date);
        $end = new DateTimeImmutable($end_date);
        $rental_days = (int) $start->diff($end)->days;

        if ($rental_days < 1 || $rental_days > 365) {
            $errors[] = 'The rental period must be between 1 and 365 days.';
        }
    }

    if (!$errors) {
        $daily_rate = (float) $vehicle['daily_rate'];
        $total_amount = $daily_rate * $rental_days;

        $_SESSION['rental_cart'] = [
            'vehicle_id' => (int) $vehicle['id'],
            'start_date' => $start_date,
            'end_date' => $end_date,
            'rental_days' => $rental_days,
            'daily_rate' => $daily_rate,
            'total_amount' => $total_amount
        ];

        log_audit(
            $conn,
            'ADD_TO_CART',
            $vehicle['brand']
                . ' '
                . $vehicle['model']
                . ' was added to the rental cart.',
            isset($_SESSION['user_id'])
                ? (int) $_SESSION['user_id']
                : null
        );

        $_SESSION['message'] = 'Vehicle added to your rental cart.';

        header('Location: leanne_cart.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo e($vehicle['brand'] . ' ' . $vehicle['model']); ?>
        | Party4U
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f6f9;
        }

        .vehicle-image {
            width: 100%;
            min-height: 420px;
            max-height: 520px;
            object-fit: cover;
            background: #e9ecef;
        }

        .vehicle-placeholder {
            min-height: 420px;
            display: grid;
            place-items: center;
            background: #e9ecef;
            color: #6c757d;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            Party4U
        </a>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="leanne_store.php">
                Browse Cars
            </a>

            <a class="btn btn-primary btn-sm" href="leanne_cart.php">
                Rental Cart
            </a>
        </div>
    </div>
</nav>

<main class="container py-5">
    <a class="text-decoration-none" href="leanne_store.php">
        ← Back to available cars
    </a>

    <?php if ($errors): ?>
        <div class="alert alert-danger mt-3">
            <strong>Please fix the following:</strong>

            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mt-3 overflow-hidden">
        <div class="row g-0">
            <div class="col-lg-7">
                <?php if (!empty($vehicle['image_path'])): ?>
                    <img
                        class="vehicle-image"
                        src="<?php echo e($vehicle['image_path']); ?>"
                        alt="<?php echo e(
                            $vehicle['brand'] . ' ' . $vehicle['model']
                        ); ?>"
                    >
                <?php else: ?>
                    <div class="vehicle-placeholder">
                        No vehicle image
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <div class="card-body p-4 p-xl-5">
                    <span class="badge text-bg-<?php
                        echo $vehicle['availability_status'] === 'Available'
                            ? 'success'
                            : 'secondary';
                    ?>">
                        <?php echo e($vehicle['availability_status']); ?>
                    </span>

                    <h1 class="h2 mt-3 mb-1">
                        <?php echo e(
                            $vehicle['brand'] . ' ' . $vehicle['model']
                        ); ?>
                    </h1>

                    <p class="text-secondary">
                        <?php echo e((string) $vehicle['year']); ?>
                        ·
                        <?php echo e($vehicle['category']); ?>
                    </p>

                    <div class="row g-3 border-top border-bottom py-3 mb-3">
                        <div class="col-6">
                            <small class="text-secondary d-block">
                                Transmission
                            </small>

                            <strong>
                                <?php echo e($vehicle['transmission']); ?>
                            </strong>
                        </div>

                        <div class="col-6">
                            <small class="text-secondary d-block">
                                Fuel Type
                            </small>

                            <strong>
                                <?php echo e($vehicle['fuel_type']); ?>
                            </strong>
                        </div>

                        <div class="col-6">
                            <small class="text-secondary d-block">Seats</small>

                            <strong>
                                <?php echo (int) $vehicle['seating_capacity']; ?>
                            </strong>
                        </div>

                        <div class="col-6">
                            <small class="text-secondary d-block">Color</small>

                            <strong>
                                <?php echo e($vehicle['color']); ?>
                            </strong>
                        </div>
                    </div>

                    <?php if (!empty($vehicle['description'])): ?>
                        <p>
                            <?php echo nl2br(e($vehicle['description'])); ?>
                        </p>
                    <?php endif; ?>

                    <div class="mb-4">
                        <strong class="fs-3">
                            ₱<?php echo number_format(
                                (float) $vehicle['daily_rate'],
                                2
                            ); ?>
                        </strong>

                        <span class="text-secondary">per day</span>
                    </div>

                    <?php if (
                        $vehicle['availability_status'] === 'Available'
                    ): ?>
                        <form method="post" novalidate>
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e(
                                    $_SESSION['csrf_token']
                                ); ?>"
                            >

                            <div class="row g-3">
                                <div class="col-sm-6">
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
                                        value="<?php echo e($start_date); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-sm-6">
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
                                        value="<?php echo e($end_date); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-12 d-grid">
                                    <button
                                        class="btn btn-primary btn-lg"
                                        type="submit"
                                    >
                                        Add to Rental Cart
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <button
                            class="btn btn-secondary btn-lg w-100"
                            disabled
                        >
                            Currently Unavailable
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
</body>
</html>
