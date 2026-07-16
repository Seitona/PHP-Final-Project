<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/includes/leanne_db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$transmission = trim((string) ($_GET['transmission'] ?? ''));

$allowed_transmissions = ['Automatic', 'Manual'];
$categories = [];
$vehicles = [];
$error_message = '';

if (!in_array($transmission, $allowed_transmissions, true)) {
    $transmission = '';
}

$category_sql = "SELECT DISTINCT category
                 FROM vehicles
                 WHERE category IS NOT NULL AND category <> ''
                 ORDER BY category";

$category_stmt = mysqli_prepare($conn, $category_sql);

if ($category_stmt) {
    if (mysqli_stmt_execute($category_stmt)) {
        $category_result = mysqli_stmt_get_result($category_stmt);

        while ($row = mysqli_fetch_assoc($category_result)) {
            $categories[] = $row['category'];
        }
    }

    mysqli_stmt_close($category_stmt);
}

if ($category !== '' && !in_array($category, $categories, true)) {
    $category = '';
}

$search_like = '%' . $search . '%';

$sql = "SELECT id, brand, model, year, category, transmission,
               fuel_type, seating_capacity, color, daily_rate,
               description, image_path, availability_status
        FROM vehicles
        WHERE availability_status = 'Available'
          AND (
              ? = ''
              OR CONCAT_WS(
                  ' ',
                  brand,
                  model,
                  year,
                  category,
                  transmission,
                  fuel_type,
                  color
              ) LIKE ?
          )
          AND (? = '' OR category = ?)
          AND (? = '' OR transmission = ?)
        ORDER BY created_at DESC, id DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        'ssssss',
        $search,
        $search_like,
        $category,
        $category,
        $transmission,
        $transmission
    );

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $vehicles = mysqli_fetch_all($result, MYSQLI_ASSOC);
    } else {
        $error_message = 'Vehicles could not be loaded right now.';
    }

    mysqli_stmt_close($stmt);
} else {
    $error_message = 'Vehicles could not be loaded right now.';
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$cart_count = isset($_SESSION['rental_cart']) ? 1 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Available Cars | Party4U</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            Party4U
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#main_navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="main_navigation">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <a class="nav-link active" href="leanne_store.php">Cars</a>
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
                        <?php echo e(
                            (string) ($_SESSION['full_name'] ?? 'Customer')
                        ); ?>
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

<header class="bg-white border-bottom">
    <div class="container py-5">
        <h1 class="display-6 fw-bold">Find your rental car</h1>

        <p class="lead text-secondary mb-0">
            Browse available vehicles and choose your rental dates.
        </p>
    </div>
</header>

<main class="container py-4">
    <?php if ($message !== ''): ?>
        <div class="alert alert-info">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <form method="get" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label for="search" class="form-label">Search</label>

                    <input
                        type="search"
                        class="form-control"
                        id="search"
                        name="search"
                        value="<?php echo e($search); ?>"
                        placeholder="Brand, model, color, or fuel type"
                    >
                </div>

                <div class="col-md-4 col-lg-3">
                    <label for="category" class="form-label">Category</label>

                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>

                        <?php foreach ($categories as $category_item): ?>
                            <option
                                value="<?php echo e($category_item); ?>"
                                <?php
                                echo $category === $category_item
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo e($category_item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label for="transmission" class="form-label">
                        Transmission
                    </label>

                    <select
                        class="form-select"
                        id="transmission"
                        name="transmission"
                    >
                        <option value="">All types</option>

                        <?php foreach ($allowed_transmissions as $type): ?>
                            <option
                                value="<?php echo e($type); ?>"
                                <?php
                                echo $transmission === $type
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo e($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2 d-grid">
                    <button class="btn btn-primary" type="submit">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Available Vehicles</h2>

        <span class="text-secondary">
            <?php echo number_format(count($vehicles)); ?> result(s)
        </span>
    </div>

    <?php if (!$vehicles && $error_message === ''): ?>
        <div class="alert alert-light border text-center py-5">
            No available vehicles matched your search.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($vehicles as $vehicle): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card car-card border-0 shadow-sm h-100">
                    <?php if (!empty($vehicle['image_path'])): ?>
                        <img
                            class="car-image card-img-top"
                            src="<?php echo e($vehicle['image_path']); ?>"
                            alt="<?php echo e(
                                $vehicle['brand'] . ' ' . $vehicle['model']
                            ); ?>"
                        >
                    <?php else: ?>
                        <div class="car-placeholder">
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
                                    ₱<?php echo number_format(
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
</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
</body>
</html>
