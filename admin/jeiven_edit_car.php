<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../includes/leanne_db.php';

// Yana:
// admin session check mo nalang ilagay dito pag ready na yung login sidee

// Leanne:
// same $conn at vehicles columns lang gamit ko dito gaya nung add vehicle page.

$db = isset($conn) && $conn instanceof mysqli ? $conn : (isset($mysqli) && $mysqli instanceof mysqli ? $mysqli : null);
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function optionSelected(string $current, string $value): string { return $current === $value ? ' selected' : ''; }

function saveReplacementImage(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) { $errors[] = 'The new image could not be uploaded.'; return null; }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) { $errors[] = 'Vehicle image must be 5 MB or smaller.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) { $errors[] = 'Only JPG, PNG, and WEBP images are allowed.'; return null; }
    $directory = __DIR__ . '/uploads/cars';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) { $errors[] = 'The image folder is not writable.'; return null; }
    $path = 'uploads/cars/' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], __DIR__ . '/' . $path)) { $errors[] = 'The new image could not be saved.'; return null; }
    return $path;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
$errors = [];
$dbError = $db === null ? 'Database connection is not ready yet. Please connect includes/leanne_db.php.' : '';
$vehicle = null;
$categories = ['Sedan','SUV','Hatchback','Pickup','Van','Coupe','Convertible'];
$transmissions = ['Automatic','Manual'];
$fuelTypes = ['Gasoline','Diesel','Hybrid','Electric'];
$statuses = ['Available','Reserved','Rented','Under Maintenance'];

if ($db !== null && $id) {
    $find = $db->prepare('SELECT * FROM vehicles WHERE id = ? LIMIT 1');
    if ($find) {
        $find->bind_param('i', $id);
        if ($find->execute()) { $result=$find->get_result(); $vehicle=$result?($result->fetch_assoc()?:null):null; }
        $find->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db !== null && $vehicle) {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Please refresh and try again.';
    $brand=trim((string)($_POST['brand']??'')); $model=trim((string)($_POST['model']??''));
    $year=filter_var($_POST['year']??null,FILTER_VALIDATE_INT); $category=trim((string)($_POST['category']??''));
    $transmission=trim((string)($_POST['transmission']??'')); $fuelType=trim((string)($_POST['fuel_type']??''));
    $seats=filter_var($_POST['seating_capacity']??null,FILTER_VALIDATE_INT); $plate=strtoupper(trim((string)($_POST['plate_number']??'')));
    $color=trim((string)($_POST['color']??'')); $dailyRate=filter_var($_POST['daily_rate']??null,FILTER_VALIDATE_FLOAT);
    $description=trim((string)($_POST['description']??'')); $status=trim((string)($_POST['availability_status']??''));
    if ($brand===''||mb_strlen($brand)>80) $errors[]='Enter a brand (maximum 80 characters).';
    if ($model===''||mb_strlen($model)>80) $errors[]='Enter a model (maximum 80 characters).';
    if ($year===false||$year<1900||$year>(int)date('Y')+1) $errors[]='Enter a valid vehicle year.';
    if (!in_array($category,$categories,true)) $errors[]='Choose a valid category.';
    if (!in_array($transmission,$transmissions,true)) $errors[]='Choose a valid transmission.';
    if (!in_array($fuelType,$fuelTypes,true)) $errors[]='Choose a valid fuel type.';
    if ($seats===false||$seats<1||$seats>100) $errors[]='Seating capacity must be between 1 and 100.';
    if ($plate===''||mb_strlen($plate)>30) $errors[]='Enter a plate number (maximum 30 characters).';
    if ($color===''||mb_strlen($color)>50) $errors[]='Enter a color (maximum 50 characters).';
    if ($dailyRate===false||$dailyRate<=0||$dailyRate>1000000) $errors[]='Enter a valid daily rental rate.';
    if (mb_strlen($description)>3000) $errors[]='Description must not exceed 3,000 characters.';
    if (!in_array($status,$statuses,true)) $errors[]='Choose a valid availability status.';
    if (!$errors) {
        $check=$db->prepare('SELECT id FROM vehicles WHERE plate_number = ? AND id <> ? LIMIT 1');
        if ($check) { $check->bind_param('si',$plate,$id); if($check->execute()){ $result=$check->get_result(); if ($result&&$result->num_rows) $errors[]='That plate number is already registered.'; }else $errors[]='Unable to validate the plate number.'; $check->close(); }
        else $errors[]='Unable to validate the plate number.';
    }
    $newImage = !$errors ? saveReplacementImage($_FILES['vehicle_image']??[], $errors) : null;
    $imagePath = $newImage ?? (string)$vehicle['image_path'];
    if (!$errors) {
        $stmt=$db->prepare('UPDATE vehicles SET brand=?, model=?, year=?, category=?, transmission=?, fuel_type=?, seating_capacity=?, plate_number=?, color=?, daily_rate=?, description=?, image_path=?, availability_status=?, updated_at=NOW() WHERE id=?');
        if ($stmt) {
            $stmt->bind_param('ssisssissdsssi',$brand,$model,$year,$category,$transmission,$fuelType,$seats,$plate,$color,$dailyRate,$description,$imagePath,$status,$id);
            if ($stmt->execute()) {
                if ($newImage && !empty($vehicle['image_path']) && str_starts_with((string)$vehicle['image_path'],'uploads/cars/')) { $old=__DIR__.'/'.$vehicle['image_path']; if (is_file($old)) unlink($old); }
                $_SESSION['flash_success']=$brand.' '.$model.' was updated successfully.'; $_SESSION['csrf_token']=bin2hex(random_bytes(32));
                header('Location: jeiven_cars.php'); exit;
            }
            $errors[]=$stmt->errno===1062?'That plate number is already registered.':'Unable to update the vehicle right now.'; $stmt->close();
        } else $errors[]='Unable to prepare the vehicle update.';
        if ($newImage && $errors && is_file(__DIR__.'/'.$newImage)) unlink(__DIR__.'/'.$newImage);
    }
    $vehicle=array_merge($vehicle,['brand'=>$brand,'model'=>$model,'year'=>$year,'category'=>$category,'transmission'=>$transmission,'fuel_type'=>$fuelType,'seating_capacity'=>$seats,'plate_number'=>$plate,'color'=>$color,'daily_rate'=>$dailyRate,'description'=>$description,'availability_status'=>$status]);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Edit Vehicle | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.sidebar{background:#17202a;min-height:100vh}.sidebar a{color:#d5d8dc;text-decoration:none}.sidebar a:hover,.sidebar a.active{background:#273746;color:#fff}.required::after{content:' *';color:#dc3545}.form-card{max-width:1050px}.current-image{width:180px;height:110px;object-fit:cover}</style></head><body><div class="container-fluid"><div class="row">
<aside class="col-md-3 col-lg-2 p-3 sidebar"><h4 class="text-white mb-4">Car Rental Admin</h4><nav class="nav flex-column gap-1"><a class="nav-link rounded" href="jeiven_dashboard.php">Dashboard</a><a class="nav-link rounded active" href="jeiven_cars.php">Vehicles</a><a class="nav-link rounded" href="jeiven_add_car.php">Add Vehicle</a><a class="nav-link rounded" href="jeiven_reports.php">Reports</a></nav><!-- Faith: same final sidebar/header mo nalang din dito para consistent lahat. --></aside>
<main class="col-md-9 col-lg-10 px-md-4 py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Edit Vehicle</h1><p class="text-secondary mb-0">Update fleet information and availability.</p></div><a class="btn btn-outline-secondary" href="jeiven_cars.php">Back to vehicles</a></div>
<?php if ($dbError): ?><div class="alert alert-warning"><?= e($dbError) ?></div><?php endif; ?>
<?php if (!$id): ?><div class="alert alert-danger">Invalid vehicle ID.</div><?php elseif ($db!==null&&!$vehicle): ?><div class="alert alert-danger">Vehicle not found.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2"><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($vehicle): ?><form method="post" enctype="multipart/form-data" class="card shadow-sm border-0 form-card" novalidate><div class="card-body p-4"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>"><div class="row g-3">
<div class="col-md-6"><label class="form-label required" for="brand">Brand</label><input class="form-control" id="brand" name="brand" maxlength="80" value="<?= e((string)$vehicle['brand']) ?>" required></div><div class="col-md-6"><label class="form-label required" for="model">Model</label><input class="form-control" id="model" name="model" maxlength="80" value="<?= e((string)$vehicle['model']) ?>" required></div>
<div class="col-md-4"><label class="form-label required" for="year">Year</label><input type="number" class="form-control" id="year" name="year" min="1900" max="<?= (int)date('Y')+1 ?>" value="<?= (int)$vehicle['year'] ?>" required></div><div class="col-md-4"><label class="form-label required" for="category">Category</label><select class="form-select" id="category" name="category" required><?php foreach($categories as $item): ?><option<?= optionSelected((string)$vehicle['category'],$item) ?>><?= e($item) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label required" for="transmission">Transmission</label><select class="form-select" id="transmission" name="transmission" required><?php foreach($transmissions as $item): ?><option<?= optionSelected((string)$vehicle['transmission'],$item) ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label required" for="fuel_type">Fuel Type</label><select class="form-select" id="fuel_type" name="fuel_type" required><?php foreach($fuelTypes as $item): ?><option<?= optionSelected((string)$vehicle['fuel_type'],$item) ?>><?= e($item) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label required" for="seating_capacity">Seating Capacity</label><input type="number" class="form-control" id="seating_capacity" name="seating_capacity" min="1" max="100" value="<?= (int)$vehicle['seating_capacity'] ?>" required></div><div class="col-md-4"><label class="form-label required" for="plate_number">Plate Number</label><input class="form-control text-uppercase" id="plate_number" name="plate_number" maxlength="30" value="<?= e((string)$vehicle['plate_number']) ?>" required></div>
<div class="col-md-4"><label class="form-label required" for="color">Color</label><input class="form-control" id="color" name="color" maxlength="50" value="<?= e((string)$vehicle['color']) ?>" required></div><div class="col-md-4"><label class="form-label required" for="daily_rate">Daily Rental Rate (PHP)</label><input type="number" class="form-control" id="daily_rate" name="daily_rate" min=".01" max="1000000" step=".01" value="<?= e((string)$vehicle['daily_rate']) ?>" required></div><div class="col-md-4"><label class="form-label required" for="availability_status">Availability Status</label><select class="form-select" id="availability_status" name="availability_status" required><?php foreach($statuses as $item): ?><option<?= optionSelected((string)$vehicle['availability_status'],$item) ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4" maxlength="3000"><?= e((string)$vehicle['description']) ?></textarea></div><div class="col-12"><label class="form-label" for="vehicle_image">Replace Vehicle Image</label><div class="d-flex flex-wrap gap-3 align-items-center"><?php if(!empty($vehicle['image_path'])): ?><img class="current-image rounded border" src="<?= e((string)$vehicle['image_path']) ?>" alt="Current vehicle image"><?php endif; ?><div class="flex-grow-1"><input type="file" class="form-control" id="vehicle_image" name="vehicle_image" accept="image/jpeg,image/png,image/webp"><div class="form-text">Leave empty to keep the current image. JPG, PNG, or WEBP; maximum 5 MB.</div></div></div></div>
</div></div><div class="card-footer bg-white p-3 text-end"><button class="btn btn-primary px-4">Save Changes</button></div></form><?php endif; ?></main></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
