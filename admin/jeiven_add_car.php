<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../includes/leanne_db.php';

// Yana:
// lagyan mo nalang ng admin session check dito pag final na login mo hehe.
// admin page lang to so paki redirect nalang yung hindi admin.

// Leanne:
// dito ko ginagamit yung $conn as mysqli connection galing sa leanne_db.php mo.
// vehicles columns: id, brand, model, year, category, transmission, fuel_type,
// seating_capacity, plate_number, color, daily_rate, description, image_path,
// availability_status, created_at, updated_at.

$db = null;
if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function old(string $key): string
{
    return e(isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : '');
}

function selected(string $key, string $value, string $default = ''): string
{
    $current = isset($_POST[$key]) && is_scalar($_POST[$key]) ? (string) $_POST[$key] : $default;
    return $current === $value ? ' selected' : '';
}

function uploadVehicleImage(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a vehicle image.';
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = 'The image could not be uploaded. Please try again.';
        return null;
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $errors[] = 'Vehicle image must be 5 MB or smaller.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
        return null;
    }

    $directory = __DIR__ . '/../assets/cars';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        $errors[] = 'The image folder is not writable.';
        return null;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
        $errors[] = 'The image could not be saved.';
        return null;
    }
    return 'assets/cars/' . $filename;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$dbError = $db === null ? 'Database connection is not ready yet. Please connect includes/leanne_db.php.' : '';
$categories = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Coupe', 'Convertible'];
$transmissions = ['Automatic', 'Manual'];
$fuelTypes = ['Gasoline', 'Diesel', 'Hybrid', 'Electric'];
$statuses = ['Available', 'Reserved', 'Rented', 'Under Maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db !== null) {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    $brand = trim((string) ($_POST['brand'] ?? ''));
    $model = trim((string) ($_POST['model'] ?? ''));
    $year = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT);
    $category = trim((string) ($_POST['category'] ?? ''));
    $transmission = trim((string) ($_POST['transmission'] ?? ''));
    $fuelType = trim((string) ($_POST['fuel_type'] ?? ''));
    $seats = filter_var($_POST['seating_capacity'] ?? null, FILTER_VALIDATE_INT);
    $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
    $color = trim((string) ($_POST['color'] ?? ''));
    $dailyRate = filter_var($_POST['daily_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $description = trim((string) ($_POST['description'] ?? ''));
    $status = trim((string) ($_POST['availability_status'] ?? 'Available'));

    if ($brand === '' || mb_strlen($brand) > 80) $errors[] = 'Enter a brand (maximum 80 characters).';
    if ($model === '' || mb_strlen($model) > 80) $errors[] = 'Enter a model (maximum 80 characters).';
    if ($year === false || $year < 1900 || $year > ((int) date('Y') + 1)) $errors[] = 'Enter a valid vehicle year.';
    if (!in_array($category, $categories, true)) $errors[] = 'Choose a valid category.';
    if (!in_array($transmission, $transmissions, true)) $errors[] = 'Choose a valid transmission.';
    if (!in_array($fuelType, $fuelTypes, true)) $errors[] = 'Choose a valid fuel type.';
    if ($seats === false || $seats < 1 || $seats > 100) $errors[] = 'Seating capacity must be between 1 and 100.';
    if ($plate === '' || mb_strlen($plate) > 30) $errors[] = 'Enter a plate number (maximum 30 characters).';
    if ($color === '' || mb_strlen($color) > 50) $errors[] = 'Enter a color (maximum 50 characters).';
    if ($dailyRate === false || $dailyRate <= 0 || $dailyRate > 1000000) $errors[] = 'Enter a valid daily rental rate.';
    if (mb_strlen($description) > 3000) $errors[] = 'Description must not exceed 3,000 characters.';
    if (!in_array($status, $statuses, true)) $errors[] = 'Choose a valid availability status.';

    if (!$errors) {
        $check = $db->prepare('SELECT id FROM vehicles WHERE plate_number = ? LIMIT 1');
        if ($check) {
            $check->bind_param('s', $plate);
            if ($check->execute()) {
                $result = $check->get_result();
                if ($result && $result->num_rows > 0) $errors[] = 'That plate number is already registered.';
            } else {
                $errors[] = 'Unable to validate the plate number.';
            }
            $check->close();
        } else {
            $errors[] = 'Unable to validate the plate number.';
        }
    }

    $imagePath = null;
    if (!$errors) $imagePath = uploadVehicleImage($_FILES['vehicle_image'] ?? [], $errors);

    if (!$errors && $imagePath !== null) {
        $stmt = $db->prepare('INSERT INTO vehicles (brand, model, year, category, transmission, fuel_type, seating_capacity, plate_number, color, daily_rate, description, image_path, availability_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        if ($stmt) {
            $stmt->bind_param('ssisssissdsss', $brand, $model, $year, $category, $transmission, $fuelType, $seats, $plate, $color, $dailyRate, $description, $imagePath, $status);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = $brand . ' ' . $model . ' was added successfully.';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: jeiven_cars.php');
                exit;
            }
            $errors[] = $stmt->errno === 1062 ? 'That plate number is already registered.' : 'Unable to save the vehicle right now.';
            $stmt->close();
        } else {
            $errors[] = 'Unable to prepare the vehicle record.';
        }
        if ($errors && is_file(__DIR__ . '/' . $imagePath)) unlink(__DIR__ . '/' . $imagePath);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Vehicle | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid"><div class="row">
    <aside class="col-md-3 col-lg-2 p-3 sidebar">
        <h4 class="text-white mb-4">Party4U Admin</h4>
        <nav class="nav flex-column gap-1">
            <a class="nav-link rounded" href="jeiven_dashboard.php">Dashboard</a>
            <a class="nav-link rounded" href="jeiven_cars.php">Vehicles</a>
            <a class="nav-link rounded active" href="jeiven_add_car.php">Add Vehicle</a>
            <a class="nav-link rounded" href="jeiven_reports.php">Reports</a>
            <a class="nav-link rounded" href="yana_users.php">Users</a>
        </nav>
        <!-- Faith: pakabit nalang dito yung final admin header/sidebar style mo hehe. -->
    </aside>
    <main class="col-md-9 col-lg-10 px-md-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h1 class="h3 mb-1">Add Vehicle</h1><p class="text-secondary mb-0">Register a new car in the fleet.</p></div>
            <a href="jeiven_cars.php" class="btn btn-outline-secondary">Back to vehicles</a>
        </div>

        <?php if ($dbError): ?><div class="alert alert-warning"><?= e($dbError) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="card shadow-sm border-0 form-card" novalidate>
            <div class="card-body p-4">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label required" for="brand">Brand</label><input class="form-control" id="brand" name="brand" maxlength="80" value="<?= old('brand') ?>" required></div>
                    <div class="col-md-6"><label class="form-label required" for="model">Model</label><input class="form-control" id="model" name="model" maxlength="80" value="<?= old('model') ?>" required></div>
                    <div class="col-md-4"><label class="form-label required" for="year">Year</label><input type="number" class="form-control" id="year" name="year" min="1900" max="<?= (int) date('Y') + 1 ?>" value="<?= old('year') ?>" required></div>
                    <div class="col-md-4"><label class="form-label required" for="category">Category</label><select class="form-select" id="category" name="category" required><option value="">Choose...</option><?php foreach ($categories as $item): ?><option<?= selected('category', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label required" for="transmission">Transmission</label><select class="form-select" id="transmission" name="transmission" required><option value="">Choose...</option><?php foreach ($transmissions as $item): ?><option<?= selected('transmission', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label required" for="fuel_type">Fuel Type</label><select class="form-select" id="fuel_type" name="fuel_type" required><option value="">Choose...</option><?php foreach ($fuelTypes as $item): ?><option<?= selected('fuel_type', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label required" for="seating_capacity">Seating Capacity</label><input type="number" class="form-control" id="seating_capacity" name="seating_capacity" min="1" max="100" value="<?= old('seating_capacity') ?>" required></div>
                    <div class="col-md-4"><label class="form-label required" for="plate_number">Plate Number</label><input class="form-control text-uppercase" id="plate_number" name="plate_number" maxlength="30" value="<?= old('plate_number') ?>" required></div>
                    <div class="col-md-4"><label class="form-label required" for="color">Color</label><input class="form-control" id="color" name="color" maxlength="50" value="<?= old('color') ?>" required></div>
                    <div class="col-md-4"><label class="form-label required" for="daily_rate">Daily Rental Rate (PHP)</label><input type="number" class="form-control" id="daily_rate" name="daily_rate" min="0.01" max="1000000" step="0.01" value="<?= old('daily_rate') ?>" required></div>
                    <div class="col-md-4"><label class="form-label required" for="availability_status">Availability Status</label><select class="form-select" id="availability_status" name="availability_status" required><?php foreach ($statuses as $item): ?><option<?= selected('availability_status', $item, 'Available') ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4" maxlength="3000"><?= old('description') ?></textarea></div>
                    <div class="col-12"><label class="form-label required" for="vehicle_image">Vehicle Image</label><input type="file" class="form-control" id="vehicle_image" name="vehicle_image" accept="image/jpeg,image/png,image/webp" required><div class="form-text">JPG, PNG, or WEBP. Maximum 5 MB.</div></div>
                </div>
            </div>
            <div class="card-footer bg-white p-3 text-end"><button class="btn btn-primary px-4" type="submit"<?= $db === null ? ' disabled' : '' ?>>Save Vehicle</button></div>
        </form>
    </main>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
