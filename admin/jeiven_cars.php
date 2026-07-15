<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../includes/leanne_db.php';

// Yana:
// paki-add nalang dito yung admin guard mo once okay na sessions natinn

// Leanne:
// $conn yung expected mysqli connection dito. Yung booking countdown gumagamit ng
// bookings columns na id, vehicle_id, end_date, status, created_at.

$db = isset($conn) && $conn instanceof mysqli ? $conn : (isset($mysqli) && $mysqli instanceof mysqli ? $mysqli : null);

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }

function statusClass(string $status): string
{
    return match ($status) {
        'Available' => 'success', 'Reserved' => 'warning', 'Rented' => 'primary',
        'Under Maintenance' => 'secondary', default => 'dark'
    };
}

function rentalTimeLabel(?string $endDate): ?array
{
    if (!$endDate) return null;
    try {
        $now = new DateTimeImmutable('now');
        $end = new DateTimeImmutable($endDate);
    } catch (Exception $exception) {
        return null;
    }
    $overdue = $end < $now;
    $seconds = abs($end->getTimestamp() - $now->getTimestamp());
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = max(1, intdiv($seconds % 3600, 60));
    $parts = [];
    if ($days > 0) $parts[] = $days . ' ' . ($days === 1 ? 'Day' : 'Days');
    if ($hours > 0 && count($parts) < 2) $parts[] = $hours . ' ' . ($hours === 1 ? 'Hour' : 'Hours');
    if (!$parts || ($days === 0 && count($parts) < 2)) $parts[] = $minutes . ' ' . ($minutes === 1 ? 'Minute' : 'Minutes');
    return ['title' => $overdue ? 'Overdue by' : 'Returns in', 'time' => implode(' ', array_slice($parts, 0, 2)), 'overdue' => $overdue];
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$dbError = $db === null ? 'Database connection is not ready yet. Please connect includes/leanne_db.php.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db !== null) {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['flash_error'] = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $vehicleId = filter_var($_POST['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$vehicleId) {
            $_SESSION['flash_error'] = 'Invalid vehicle selected.';
        } else {
            $imagePath = '';
            $lookup = $db->prepare('SELECT image_path FROM vehicles WHERE id = ? LIMIT 1');
            if ($lookup) {
                $lookup->bind_param('i', $vehicleId);
                $lookup->execute();
                $record = $lookup->get_result()->fetch_assoc();
                $imagePath = (string) ($record['image_path'] ?? '');
                $lookup->close();
            }
            $stmt = $db->prepare("DELETE FROM vehicles WHERE id = ? AND availability_status NOT IN ('Reserved', 'Rented')");
            if ($stmt) {
                $stmt->bind_param('i', $vehicleId);
                if ($stmt->execute() && $stmt->affected_rows === 1) {
                    if ($imagePath !== '' && str_starts_with($imagePath, 'uploads/cars/')) {
                        $fullPath = __DIR__ . '/' . $imagePath;
                        if (is_file($fullPath)) unlink($fullPath);
                    }
                    $_SESSION['flash_success'] = 'Vehicle deleted successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Vehicle cannot be deleted while reserved/rented or linked to a booking.';
                }
                $stmt->close();
            } else {
                $_SESSION['flash_error'] = 'Unable to delete the vehicle right now.';
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: jeiven_cars.php');
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['', 'Available', 'Reserved', 'Rented', 'Under Maintenance'];
if (!in_array($status, $allowedStatuses, true)) $status = '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$total = 0;
$vehicles = [];

if ($db !== null) {
    $like = '%' . $search . '%';
    $count = $db->prepare("SELECT COUNT(*) AS total FROM vehicles WHERE (? = '' OR CONCAT_WS(' ', brand, model, plate_number, category, color) LIKE ?) AND (? = '' OR availability_status = ?)");
    if ($count) {
        $count->bind_param('ssss', $search, $like, $status, $status);
        if ($count->execute()) {
            $result = $count->get_result();
            $total = (int) (($result ? $result->fetch_assoc() : [])['total'] ?? 0);
        }
        $count->close();
    }
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT v.*, (SELECT b.end_date FROM bookings b WHERE b.vehicle_id = v.id AND b.status = 'Rented' ORDER BY b.end_date DESC, b.id DESC LIMIT 1) AS rental_end_date FROM vehicles v WHERE (? = '' OR CONCAT_WS(' ', v.brand, v.model, v.plate_number, v.category, v.color) LIKE ?) AND (? = '' OR v.availability_status = ?) ORDER BY v.created_at DESC, v.id DESC LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ssssii', $search, $like, $status, $status, $perPage, $offset);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $vehicles = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $dbError = 'Vehicle list could not be loaded. Please check the vehicles/bookings table setup.';
        }
        $stmt->close();
    } else {
        $dbError = 'Vehicle list could not be loaded. Please check the vehicles/bookings table setup.';
    }
} else {
    $pages = 1;
}

$success = (string) ($_SESSION['flash_success'] ?? '');
$error = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vehicles | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f6f9}.sidebar{background:#17202a;min-height:100vh}.sidebar a{color:#d5d8dc;text-decoration:none}.sidebar a:hover,.sidebar a.active{background:#273746;color:#fff}.vehicle-thumb{width:92px;height:62px;object-fit:cover;background:#e9ecef}.countdown{font-size:.78rem;line-height:1.2;white-space:nowrap}</style>
</head><body><div class="container-fluid"><div class="row">
<aside class="col-md-3 col-lg-2 p-3 sidebar"><h4 class="text-white mb-4">Car Rental Admin</h4><nav class="nav flex-column gap-1"><a class="nav-link rounded" href="jeiven_dashboard.php">Dashboard</a><a class="nav-link rounded active" href="jeiven_cars.php">Vehicles</a><a class="nav-link rounded" href="jeiven_add_car.php">Add Vehicle</a><a class="nav-link rounded" href="jeiven_reports.php">Reports</a></nav><!-- Faith: final sidebar design mo nalang ikabit dito, salamat hehe. --></aside>
<main class="col-md-9 col-lg-10 px-md-4 py-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="h3 mb-1">Vehicles</h1><p class="text-secondary mb-0"><?= number_format($total) ?> vehicle<?= $total === 1 ? '' : 's' ?> in the fleet</p></div><a class="btn btn-primary" href="jeiven_add_car.php">+ Add Vehicle</a></div>
<?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= e($success) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($dbError): ?><div class="alert alert-warning"><?= e($dbError) ?></div><?php endif; ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><form class="row g-2" method="get"><div class="col-md-7"><label class="visually-hidden" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Search brand, model, plate, category, or color"></div><div class="col-md-3"><label class="visually-hidden" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach (array_slice($allowedStatuses, 1) as $item): ?><option value="<?= e($item) ?>"<?= $status === $item ? ' selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-grid"><button class="btn btn-dark">Filter</button></div></form></div></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Vehicle</th><th>Details</th><th>Plate</th><th>Daily Rate</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if (!$vehicles): ?><tr><td colspan="6" class="text-center text-secondary py-5">No vehicles found.</td></tr><?php endif; ?>
<?php foreach ($vehicles as $vehicle): $timer = $vehicle['availability_status'] === 'Rented' ? rentalTimeLabel($vehicle['rental_end_date'] ?? null) : null; ?>
<tr><td><div class="d-flex align-items-center gap-3"><?php if (!empty($vehicle['image_path'])): ?><img class="vehicle-thumb rounded" src="<?= e((string) $vehicle['image_path']) ?>" alt="<?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>"><?php else: ?><div class="vehicle-thumb rounded d-flex align-items-center justify-content-center text-secondary">No image</div><?php endif; ?><div><strong><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></strong><div class="text-secondary small"><?= (int) $vehicle['year'] ?> · <?= e($vehicle['color']) ?></div></div></div></td><td><div><?= e($vehicle['category']) ?></div><small class="text-secondary"><?= e($vehicle['transmission']) ?> · <?= e($vehicle['fuel_type']) ?> · <?= (int) $vehicle['seating_capacity'] ?> seats</small></td><td><?= e($vehicle['plate_number']) ?></td><td>₱<?= number_format((float) $vehicle['daily_rate'], 2) ?></td><td><span class="badge text-bg-<?= statusClass((string) $vehicle['availability_status']) ?>"><?= e($vehicle['availability_status']) ?></span><?php if ($timer): ?><div class="countdown mt-2 <?= $timer['overdue'] ? 'text-danger' : 'text-primary' ?>"><strong><?= e($timer['title']) ?></strong><br><?= e($timer['time']) ?></div><?php endif; ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="jeiven_edit_car.php?id=<?= (int) $vehicle['id'] ?>">Edit</a> <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $vehicle['id'] ?>" data-name="<?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>"<?= in_array($vehicle['availability_status'], ['Reserved','Rented'], true) ? ' disabled' : '' ?>>Delete</button></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if ($pages > 1): ?><div class="card-footer bg-white"><nav aria-label="Vehicle pages"><ul class="pagination pagination-sm mb-0 justify-content-end"><?php for ($i=1;$i<=$pages;$i++): ?><li class="page-item<?= $i===$page?' active':'' ?>"><a class="page-link" href="?<?= http_build_query(['search'=>$search,'status'=>$status,'page'=>$i]) ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav></div><?php endif; ?></div>
</main></div></div>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Delete Vehicle</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body">Delete <strong id="deleteName"></strong>? This cannot be undone.</div><div class="modal-footer"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="vehicle_id" id="deleteId"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>document.getElementById('deleteModal').addEventListener('show.bs.modal',function(event){const button=event.relatedTarget;document.getElementById('deleteId').value=button.dataset.id;document.getElementById('deleteName').textContent=button.dataset.name;});</script>
</body></html>
