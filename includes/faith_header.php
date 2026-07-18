<?php
declare(strict_types=1);

$page_title = $page_title ?? 'Party4U';
$active_page = $active_page ?? '';
$cart_count = isset($_SESSION['rental_cart']) ? 1 : 0;
$customer_name = (string) ($_SESSION['full_name'] ?? 'Customer');

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">Party4U</a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#faith_navigation"
            aria-controls="faith_navigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="faith_navigation">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <a class="nav-link<?php echo $active_page === 'home' ? ' active' : ''; ?>" href="index.php">
                    Home
                </a>
                <a class="nav-link<?php echo $active_page === 'cars' ? ' active' : ''; ?>" href="leanne_store.php">
                    Cars
                </a>
                <a class="nav-link<?php echo $active_page === 'about' ? ' active' : ''; ?>" href="faith_about.php">
                    About
                </a>
                <a class="btn btn-outline-light btn-sm" href="leanne_cart.php">
                    Rental Cart
                    <?php if ($cart_count > 0): ?>
                        <span class="badge text-bg-danger ms-1"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="navbar-text"><?php echo e($customer_name); ?></span>
                    <a class="btn btn-danger btn-sm" href="yana_logout.php">Logout</a>
                <?php else: ?>
                    <a class="btn btn-primary btn-sm" href="yana_login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
