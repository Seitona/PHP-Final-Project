<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../includes/leanne_db.php';

// Yana:
// admin guard mo nalang dito kapag final na roles/session names mo hehe

// Leanne:
// $conn ang mysqli connection. Dashboard expects bookings: id, vehicle_id,
// start_date, end_date, total_amount, status, created_at.

// Faith:
// after successful payment, update mo nalang booking status/total_amount.
// Dito ko kinukuha revenue from Completed bookings para di tayo dependent sa payment table mo.

$db = isset($conn) && $conn instanceof mysqli ? $conn : (isset($mysqli) && $mysqli instanceof mysqli ? $mysqli : null);
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function fetchRows(mysqli $db, string $sql): array { $stmt=$db->prepare($sql); if(!$stmt)return[]; if(!$stmt->execute()){$stmt->close();return[];} $result=$stmt->get_result();$rows=$result?$result->fetch_all(MYSQLI_ASSOC):[]; $stmt->close(); return $rows; }
function fetchValue(mysqli $db, string $sql): float { $stmt=$db->prepare($sql); if(!$stmt)return 0; if(!$stmt->execute()){$stmt->close();return 0;} $result=$stmt->get_result();$row=$result?$result->fetch_row():[]; $stmt->close(); return (float)($row[0]??0); }
function badgeClass(string $status): string { return match($status){'Available','Completed'=>'success','Reserved','Pending'=>'warning','Rented'=>'primary','Under Maintenance'=>'secondary','Cancelled','Overdue'=>'danger',default=>'dark'}; }
function dueLabel(string $endDate): array
{
    try { $now=new DateTimeImmutable('now'); $end=new DateTimeImmutable($endDate); }
    catch(Exception $e){ return ['label'=>'Unknown','overdue'=>false]; }
    $overdue=$end<$now; $seconds=abs($end->getTimestamp()-$now->getTimestamp());
    $days=intdiv($seconds,86400); $hours=intdiv($seconds%86400,3600); $minutes=max(1,intdiv($seconds%3600,60)); $parts=[];
    if($days)$parts[]=$days.' '.($days===1?'Day':'Days');
    if($hours&&count($parts)<2)$parts[]=$hours.' '.($hours===1?'Hour':'Hours');
    if(!$parts||(!$days&&count($parts)<2))$parts[]=$minutes.' '.($minutes===1?'Minute':'Minutes');
    return ['label'=>($overdue?'Overdue by ':'Returns in ').implode(' ',array_slice($parts,0,2)),'overdue'=>$overdue];
}

$stats=['total_cars'=>0,'available'=>0,'rented'=>0,'reserved'=>0,'maintenance'=>0,'returning_today'=>0,'overdue'=>0,'revenue'=>0,'bookings'=>0];
$recentRentals=$returningSoon=$mostRented=$recentCars=[];
$dbError=$db===null?'Database connection is not ready yet. Please connect includes/leanne_db.php.':'';
if($db!==null){
    $stats['total_cars']=(int)fetchValue($db,'SELECT COUNT(*) FROM vehicles');
    $stats['available']=(int)fetchValue($db,"SELECT COUNT(*) FROM vehicles WHERE availability_status='Available'");
    $stats['rented']=(int)fetchValue($db,"SELECT COUNT(*) FROM vehicles WHERE availability_status='Rented'");
    $stats['reserved']=(int)fetchValue($db,"SELECT COUNT(*) FROM vehicles WHERE availability_status='Reserved'");
    $stats['maintenance']=(int)fetchValue($db,"SELECT COUNT(*) FROM vehicles WHERE availability_status='Under Maintenance'");
    $stats['returning_today']=(int)fetchValue($db,"SELECT COUNT(*) FROM bookings WHERE status='Rented' AND end_date>=CURDATE() AND end_date<DATE_ADD(CURDATE(),INTERVAL 1 DAY)");
    $stats['overdue']=(int)fetchValue($db,"SELECT COUNT(*) FROM bookings WHERE status='Rented' AND end_date<NOW()");
    $stats['revenue']=fetchValue($db,"SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE status='Completed'");
    $stats['bookings']=(int)fetchValue($db,'SELECT COUNT(*) FROM bookings');
    $recentRentals=fetchRows($db,"SELECT b.id,b.start_date,b.end_date,b.total_amount,b.status,b.created_at,v.brand,v.model,v.plate_number FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id ORDER BY b.created_at DESC,b.id DESC LIMIT 6");
    $returningSoon=fetchRows($db,"SELECT b.id,b.end_date,v.id AS vehicle_id,v.brand,v.model,v.plate_number FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.status='Rented' AND b.end_date<=DATE_ADD(NOW(),INTERVAL 3 DAY) ORDER BY b.end_date ASC LIMIT 6");
    $mostRented=fetchRows($db,"SELECT v.id,v.brand,v.model,v.plate_number,COUNT(b.id) AS rental_count FROM vehicles v JOIN bookings b ON b.vehicle_id=v.id AND b.status<>'Cancelled' GROUP BY v.id,v.brand,v.model,v.plate_number ORDER BY rental_count DESC,v.brand,v.model LIMIT 5");
    $recentCars=fetchRows($db,'SELECT id,brand,model,plate_number,availability_status,created_at,image_path FROM vehicles ORDER BY created_at DESC,id DESC LIMIT 5');
}
$cards=[
 ['Total Cars',$stats['total_cars'],'primary'],['Available Cars',$stats['available'],'success'],['Rented Cars',$stats['rented'],'info'],
 ['Reserved Cars',$stats['reserved'],'warning'],['Under Maintenance',$stats['maintenance'],'secondary'],['Returning Today',$stats['returning_today'],'primary'],
 ['Overdue Rentals',$stats['overdue'],'danger'],['Total Revenue','₱'.number_format($stats['revenue'],2),'success'],['Total Bookings',$stats['bookings'],'dark']
];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dashboard | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.sidebar{background:#17202a;min-height:100vh}.sidebar a{color:#d5d8dc;text-decoration:none}.sidebar a:hover,.sidebar a.active{background:#273746;color:#fff}.metric{border-left:4px solid var(--bs-primary)}.car-thumb{width:55px;height:40px;object-fit:cover;background:#e9ecef}.table td,.table th{white-space:nowrap}</style></head><body><div class="container-fluid"><div class="row">
<aside class="col-md-3 col-lg-2 p-3 sidebar"><h4 class="text-white mb-4">Party4U Admin</h4><nav class="nav flex-column gap-1"><a class="nav-link rounded active" href="jeiven_dashboard.php">Dashboard</a><a class="nav-link rounded" href="jeiven_cars.php">Vehicles</a><a class="nav-link rounded" href="jeiven_add_car.php">Add Vehicle</a><a class="nav-link rounded" href="jeiven_reports.php">Reports</a></nav><!-- Faith: pakabit nalang final header/sidebar mo dito para match sa public pages hehe. --></aside>
<main class="col-md-9 col-lg-10 px-md-4 py-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="h3 mb-1">Dashboard</h1><p class="text-secondary mb-0">Fleet and rental overview as of <?= e(date('M j, Y g:i A')) ?></p></div><a class="btn btn-primary" href="jeiven_add_car.php">+ Add Vehicle</a></div>
<?php if($dbError):?><div class="alert alert-warning"><?=e($dbError)?></div><?php endif;?>
<div class="row g-3 mb-4"><?php foreach($cards as [$label,$value,$color]):?><div class="col-sm-6 col-xl-4"><div class="card border-0 shadow-sm h-100 metric border-<?=e($color)?>"><div class="card-body"><div class="text-secondary small text-uppercase fw-semibold"><?=e($label)?></div><div class="fs-3 fw-bold mt-1"><?=is_int($value)?number_format($value):e((string)$value)?></div></div></div></div><?php endforeach;?></div>
<div class="row g-4 mb-4"><div class="col-xl-8"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Recent Rentals</strong><a href="jeiven_reports.php?report=bookings" class="small">View report</a></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Booking</th><th>Vehicle</th><th>Rental Dates</th><th>Total</th><th>Status</th></tr></thead><tbody><?php if(!$recentRentals):?><tr><td colspan="5" class="text-center text-secondary py-4">No rental records yet.</td></tr><?php endif;?><?php foreach($recentRentals as $row):?><tr><td>#<?= (int)$row['id']?></td><td><strong><?=e($row['brand'].' '.$row['model'])?></strong><div class="small text-secondary"><?=e($row['plate_number'])?></div></td><td><?=e(date('M j',strtotime($row['start_date'])))?> – <?=e(date('M j, Y',strtotime($row['end_date'])))?></td><td>₱<?=number_format((float)$row['total_amount'],2)?></td><td><span class="badge text-bg-<?=badgeClass((string)$row['status'])?>"><?=e($row['status'])?></span></td></tr><?php endforeach;?></tbody></table></div></div></div>
<div class="col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Vehicles Returning Soon</strong></div><div class="list-group list-group-flush"><?php if(!$returningSoon):?><div class="text-center text-secondary p-4">No vehicles due soon.</div><?php endif;?><?php foreach($returningSoon as $row):$due=dueLabel((string)$row['end_date']);?><a href="jeiven_edit_car.php?id=<?=(int)$row['vehicle_id']?>" class="list-group-item list-group-item-action d-flex justify-content-between gap-2"><div><strong><?=e($row['brand'].' '.$row['model'])?></strong><div class="small text-secondary"><?=e($row['plate_number'])?></div></div><small class="text-end fw-semibold <?=$due['overdue']?'text-danger':'text-primary'?>"><?=e($due['label'])?></small></a><?php endforeach;?></div></div></div></div>
<div class="row g-4"><div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Most Rented Vehicles</strong></div><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>#</th><th>Vehicle</th><th>Plate</th><th class="text-end">Rentals</th></tr></thead><tbody><?php if(!$mostRented):?><tr><td colspan="4" class="text-center text-secondary py-4">No rental data yet.</td></tr><?php endif;?><?php foreach($mostRented as $index=>$row):?><tr><td><?= $index+1?></td><td><a href="jeiven_edit_car.php?id=<?=(int)$row['id']?>" class="text-decoration-none fw-semibold"><?=e($row['brand'].' '.$row['model'])?></a></td><td><?=e($row['plate_number'])?></td><td class="text-end"><?=number_format((int)$row['rental_count'])?></td></tr><?php endforeach;?></tbody></table></div></div></div>
<div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white d-flex justify-content-between"><strong>Recent Added Cars</strong><a class="small" href="jeiven_cars.php">View all</a></div><div class="list-group list-group-flush"><?php if(!$recentCars):?><div class="text-center text-secondary p-4">No vehicles added yet.</div><?php endif;?><?php foreach($recentCars as $row):?><a href="jeiven_edit_car.php?id=<?=(int)$row['id']?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3"><?php if(!empty($row['image_path'])): $imagePath = str_starts_with((string)$row['image_path'], 'assets/') ? '../' . $row['image_path'] : (string)$row['image_path']; ?><img class="car-thumb rounded" src="<?=e($imagePath)?>" alt=""><?php else:?><div class="car-thumb rounded"></div><?php endif;?><div class="flex-grow-1"><strong><?=e($row['brand'].' '.$row['model'])?></strong><div class="small text-secondary"><?=e($row['plate_number'])?> · Added <?=e(date('M j, Y',strtotime($row['created_at'])))?></div></div><span class="badge text-bg-<?=badgeClass((string)$row['availability_status'])?>"><?=e($row['availability_status'])?></span></a><?php endforeach;?></div></div></div></div>
</main></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
