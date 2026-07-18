<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);
define('ALLOW_DB_FAILURE', true);
require_once __DIR__ . '/includes/leanne_db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function fetch_count(?mysqli $conn, string $sql): int
{
    if (!$conn) {
        return 0;
    }

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        return 0;
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_row($result) : [0];

    mysqli_stmt_close($stmt);

    return (int) ($row[0] ?? 0);
}

function fetch_value(?mysqli $conn, string $sql): string
{
    if (!$conn) {
        return '';
    }

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        return '';
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_row($result) : [''];

    mysqli_stmt_close($stmt);

    return (string) ($row[0] ?? '');
}

$database_error = $db_connection_error;
$featured_vehicles = [];
$cart_count = isset($_SESSION['rental_cart']) ? 1 : 0;
$customer_name = (string) ($_SESSION['full_name'] ?? 'Customer');

$total_vehicles = fetch_count($conn, 'SELECT COUNT(*) FROM vehicles');
$available_vehicles = fetch_count(
    $conn,
    "SELECT COUNT(*) FROM vehicles WHERE availability_status = 'Available'"
);
$category_count = fetch_count(
    $conn,
    "SELECT COUNT(DISTINCT category)
     FROM vehicles
     WHERE category IS NOT NULL AND category <> ''"
);
$lowest_rate = fetch_value(
    $conn,
    "SELECT MIN(daily_rate)
     FROM vehicles
     WHERE availability_status = 'Available'"
);

$featured_sql = "SELECT id, brand, model, year, category, transmission,
                        fuel_type, seating_capacity, color, daily_rate,
                        image_path
                 FROM vehicles
                 WHERE availability_status = 'Available'
                 ORDER BY created_at DESC, id DESC
                 LIMIT 3";

$featured_stmt = $conn ? mysqli_prepare($conn, $featured_sql) : false;

if ($featured_stmt && mysqli_stmt_execute($featured_stmt)) {
    $featured_result = mysqli_stmt_get_result($featured_stmt);
    $featured_vehicles = $featured_result
        ? mysqli_fetch_all($featured_result, MYSQLI_ASSOC)
        : [];
}

if ($featured_stmt) {
    mysqli_stmt_close($featured_stmt);
}

$stats = [
    [
        'label' => 'Fleet Vehicles',
        'value' => number_format($total_vehicles)
    ],
    [
        'label' => 'Available Now',
        'value' => number_format($available_vehicles)
    ],
    [
        'label' => 'Vehicle Types',
        'value' => number_format($category_count)
    ],
    [
        'label' => 'Rates From',
        'value' => $lowest_rate !== ''
            ? 'PHP ' . number_format((float) $lowest_rate, 2)
            : 'PHP 0.00'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Party4U | Drive Your Next Trip</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            Party4U
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#main_navigation"
            aria-controls="main_navigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="main_navigation">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <a class="nav-link active" href="index.php">Home</a>
                <a class="nav-link" href="leanne_store.php">Cars</a>
                <a class="nav-link" href="faith_about.php">About</a>

                <a class="btn btn-outline-light btn-sm" href="leanne_cart.php">
                    Rental Cart

                    <?php if ($cart_count > 0): ?>
                        <span class="badge text-bg-danger ms-1">
                            <?php echo $cart_count; ?>
                        </span>
                    <?php endif; ?>
                </a>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="navbar-text">
                        <?php echo e($customer_name); ?>
                    </span>

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
    </div>
</nav>

<header class="hero">
    <div class="container py-5">
        <div class="hero-panel">
            <span class="badge text-bg-light mb-3">
                Fast local car reservations
            </span>

            <h1 class="display-4 fw-bold mb-3">
                Rent the right car for every drive.
            </h1>

            <p class="lead mb-4">
                Browse available vehicles, choose your rental dates, and
                continue through checkout with a simple reservation flow.
            </p>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <a class="btn btn-primary btn-lg" href="leanne_store.php">
                    Browse Available Cars
                </a>

                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a class="btn btn-outline-light btn-lg" href="yana_register.php">
                        Create Account
                    </a>
                <?php else: ?>
                    <a class="btn btn-outline-light btn-lg" href="leanne_cart.php">
                        View Rental Cart
                    </a>
                <?php endif; ?>
            </div>

            <form method="get" action="leanne_store.php" class="search-box p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md">
                        <label class="visually-hidden" for="hero_search">
                            Search cars
                        </label>

                        <input
                            type="search"
                            class="form-control form-control-lg"
                            id="hero_search"
                            name="search"
                            placeholder="Search brand, model, color, or fuel type"
                        >
                    </div>

                    <div class="col-md-auto d-grid">
                        <button class="btn btn-dark btn-lg" type="submit">
                            Find Cars
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</header>

<main>
    <?php if ($database_error !== ''): ?>
        <section class="container pt-4">
            <div class="alert alert-warning mb-0">
                <?php echo e($database_error); ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="stat-band">
        <div class="container">
            <div class="row g-3">
                <?php foreach ($stats as $stat): ?>
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="text-secondary small text-uppercase fw-semibold">
                                    <?php echo e($stat['label']); ?>
                                </div>

                                <div class="fs-4 fw-bold mt-1">
                                    <?php echo e($stat['value']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-title mb-4">
            <h2 class="h1 fw-bold">Featured available cars</h2>

            <p class="text-secondary mb-0">
                These vehicles are pulled from the current fleet inventory.
            </p>
        </div>

        <?php if (!$featured_vehicles): ?>
            <div class="alert alert-light border text-center py-5">
                No available vehicles are listed yet.
                <a href="leanne_store.php">View the full cars page</a>.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($featured_vehicles as $vehicle): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card vehicle-card h-100 overflow-hidden">
                            <?php if (!empty($vehicle['image_path'])): ?>
                                <img
                                    class="vehicle-image card-img-top"
                                    src="<?php echo e($vehicle['image_path']); ?>"
                                    alt="<?php echo e(
                                        $vehicle['brand']
                                        . ' '
                                        . $vehicle['model']
                                    ); ?>"
                                >
                            <?php else: ?>
                                <div class="vehicle-placeholder">
                                    No vehicle image
                                </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <h3 class="h5 mb-1">
                                            <?php echo e(
                                                $vehicle['brand']
                                                . ' '
                                                . $vehicle['model']
                                            ); ?>
                                        </h3>

                                        <span class="text-secondary">
                                            <?php echo e((string) $vehicle['year']); ?>
                                            ·
                                            <?php echo e($vehicle['category']); ?>
                                        </span>
                                    </div>

                                    <span class="badge text-bg-success align-self-start">
                                        Available
                                    </span>
                                </div>

                                <div class="row small text-secondary mt-3">
                                    <div class="col-6 mb-2">
                                        <?php echo e($vehicle['transmission']); ?>
                                    </div>

                                    <div class="col-6 mb-2">
                                        <?php echo e($vehicle['fuel_type']); ?>
                                    </div>

                                    <div class="col-6">
                                        <?php echo (int) $vehicle['seating_capacity']; ?>
                                        seats
                                    </div>

                                    <div class="col-6">
                                        <?php echo e($vehicle['color']); ?>
                                    </div>
                                </div>

                                <div class="mt-auto pt-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="fs-5">
                                            PHP <?php echo number_format(
                                                (float) $vehicle['daily_rate'],
                                                2
                                            ); ?>
                                        </strong>

                                        <small class="text-secondary">/ day</small>
                                    </div>

                                    <a
                                        class="btn btn-primary"
                                        href="leanne_product.php?id=<?php
                                            echo (int) $vehicle['id'];
                                        ?>"
                                    >
                                        View Car
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-4">
                <a class="btn btn-outline-dark" href="leanne_store.php">
                    View All Available Cars
                </a>
            </div>
        <?php endif; ?>
    </section>

    <section class="bg-white border-top border-bottom">
        <div class="container py-5">
            <div class="section-title mb-4">
                <h2 class="h1 fw-bold">Why rent with us?</h2>

                <p class="text-secondary mb-0">
                    A straightforward rental system for customers and fleet
                    managers.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card card h-100">
                        <div class="card-body">
                            <div class="feature-icon mb-3">01</div>

                            <h3 class="h5">Live vehicle availability</h3>

                            <p class="text-secondary mb-0">
                                Customers see cars currently marked available
                                by the admin fleet manager.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card card h-100">
                        <div class="card-body">
                            <div class="feature-icon mb-3">02</div>

                            <h3 class="h5">Simple rental cart</h3>

                            <p class="text-secondary mb-0">
                                Pick dates, review the daily rate, and confirm
                                the total before checkout.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card card h-100">
                        <div class="card-body">
                            <div class="feature-icon mb-3">03</div>

                            <h3 class="h5">Managed fleet records</h3>

                            <p class="text-secondary mb-0">
                                Admin pages track vehicles, bookings, reports,
                                and availability statuses.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5">
                <h2 class="h1 fw-bold">Reserve in three steps</h2>

                <p class="text-secondary">
                    The website is built around the existing browse, product,
                    cart, login, and checkout pages.
                </p>
            </div>

            <div class="col-lg-7">
                <div class="vstack gap-3">
                    <div class="d-flex gap-3">
                        <span class="step-number">1</span>

                        <div>
                            <h3 class="h5 mb-1">Choose a vehicle</h3>

                            <p class="text-secondary mb-0">
                                Use filters in the cars page to find the brand,
                                category, or transmission you prefer.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <span class="step-number">2</span>

                        <div>
                            <h3 class="h5 mb-1">Select rental dates</h3>

                            <p class="text-secondary mb-0">
                                Add a car to the rental cart with pickup and
                                return dates.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <span class="step-number">3</span>

                        <div>
                            <h3 class="h5 mb-1">Continue to checkout</h3>

                            <p class="text-secondary mb-0">
                                Login or create an account, then proceed to the
                                checkout and payment pages.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="container py-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg">
                    <h2 class="h1 fw-bold mb-2">
                        Ready to start your reservation?
                    </h2>

                    <p class="lead mb-0">
                        Check the latest available cars and pick the one that
                        fits your trip.
                    </p>
                </div>

                <div class="col-lg-auto">
                    <a class="btn btn-primary btn-lg" href="leanne_store.php">
                        Browse Cars
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="bg-white border-top">
    <div class="container py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
            <span class="fw-semibold">Party4U</span>

            <div class="text-secondary">
                <div>Fleet browsing, reservation cart, checkout, and admin tools.</div>
                <small>This website is for educational purposes only and is a requirement for our final project.</small>
            </div>
        </div>
    </div>
</footer>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
</body>
</html>
