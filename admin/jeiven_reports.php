<?php
declare(strict_types=1);

session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../includes/leanne_db.php';

// Yana:
// admin-only check mo nalang dito pag final na authentication mo.

// Leanne:
// reports use $conn plus vehicles/bookings contract na nilagay ko sa dashboard comments.

// Faith:
// Completed booking total_amount muna basehan ng revenue report.
// Pag may final payments table ka na, sabihan mo ko para mapalitan natin query nang hindi ginagalaw flow mo.

$db=isset($conn)&&$conn instanceof mysqli?$conn:(isset($mysqli)&&$mysqli instanceof mysqli?$mysqli:null);
function e(?string $value):string{return htmlspecialchars($value??'',ENT_QUOTES,'UTF-8');}
function validDate(string $date,string $fallback):string{$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);return $parsed&&$parsed->format('Y-m-d')===$date?$date:$fallback;}
function reportRows(mysqli $db,string $sql,string $types='',array $params=[]):array
{
    $stmt=$db->prepare($sql); if(!$stmt)return[];
    if($types!==''){$bind=[$types];foreach($params as $key=>&$value)$bind[]=&$value;call_user_func_array([$stmt,'bind_param'],$bind);}
    if(!$stmt->execute()){$stmt->close();return[];}$result=$stmt->get_result();$rows=$result?$result->fetch_all(MYSQLI_ASSOC):[];$stmt->close();return $rows;
}
function reportBadge(string $status):string{return match($status){'Available','Completed'=>'success','Reserved','Pending'=>'warning','Rented'=>'primary','Under Maintenance'=>'secondary','Cancelled'=>'danger',default=>'dark'};}
function officialDue(string $endDate):string
{
    try{$now=new DateTimeImmutable('now');$end=new DateTimeImmutable($endDate);}catch(Exception $e){return'Unknown';}
    $overdue=$end<$now;$seconds=abs($end->getTimestamp()-$now->getTimestamp());$days=intdiv($seconds,86400);$hours=intdiv($seconds%86400,3600);$minutes=max(1,intdiv($seconds%3600,60));$parts=[];
    if($days)$parts[]=$days.' '.($days===1?'Day':'Days');if($hours&&count($parts)<2)$parts[]=$hours.' '.($hours===1?'Hour':'Hours');if(!$parts||(!$days&&count($parts)<2))$parts[]=$minutes.' '.($minutes===1?'Minute':'Minutes');
    return($overdue?'Overdue by ':'Returns in ').implode(' ',array_slice($parts,0,2));
}

$reports=['vehicle-status'=>'Vehicle Status','bookings'=>'Bookings','revenue'=>'Revenue','most-rented'=>'Most Rented Cars','returning-today'=>'Returning Today','overdue'=>'Overdue Rentals','pending'=>'Pending Rentals','completed'=>'Completed Rentals','cancelled'=>'Cancelled Rentals'];
$report=(string)($_GET['report']??'vehicle-status');if(!isset($reports[$report]))$report='vehicle-status';
$today=date('Y-m-d');$from=validDate((string)($_GET['from']??date('Y-m-01')),date('Y-m-01'));$to=validDate((string)($_GET['to']??$today),$today);if($from>$to){[$from,$to]=[$to,$from];}
$rows=[];$dbError=$db===null?'Database connection is not ready yet. Please connect includes/leanne_db.php.':'';
if($db!==null){
    $range=' b.created_at >= ? AND b.created_at < DATE_ADD(?, INTERVAL 1 DAY) ';
    switch($report){
        case'vehicle-status':$rows=reportRows($db,"SELECT availability_status AS status,COUNT(*) AS total,COALESCE(SUM(daily_rate),0) AS combined_daily_rate FROM vehicles GROUP BY availability_status ORDER BY FIELD(availability_status,'Available','Reserved','Rented','Under Maintenance')");break;
        case'revenue':$rows=reportRows($db,"SELECT DATE(b.created_at) AS report_date,COUNT(*) AS bookings,SUM(b.total_amount) AS revenue FROM bookings b WHERE b.status='Completed' AND $range GROUP BY DATE(b.created_at) ORDER BY report_date DESC",'ss',[$from,$to]);break;
        case'most-rented':$rows=reportRows($db,"SELECT v.id,v.brand,v.model,v.plate_number,COUNT(b.id) AS rental_count,COALESCE(SUM(CASE WHEN b.status='Completed' THEN b.total_amount ELSE 0 END),0) AS revenue FROM vehicles v JOIN bookings b ON b.vehicle_id=v.id WHERE b.status<>'Cancelled' AND $range GROUP BY v.id,v.brand,v.model,v.plate_number ORDER BY rental_count DESC,v.brand,v.model",'ss',[$from,$to]);break;
        case'returning-today':$rows=reportRows($db,"SELECT b.id,v.brand,v.model,v.plate_number,b.start_date,b.end_date,b.total_amount,b.status FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.status='Rented' AND b.end_date>=CURDATE() AND b.end_date<DATE_ADD(CURDATE(),INTERVAL 1 DAY) ORDER BY b.end_date");break;
        case'overdue':$rows=reportRows($db,"SELECT b.id,v.brand,v.model,v.plate_number,b.start_date,b.end_date,b.total_amount,b.status FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.status='Rented' AND b.end_date<NOW() ORDER BY b.end_date");break;
        case'pending':$rows=reportRows($db,"SELECT b.id,v.brand,v.model,v.plate_number,b.start_date,b.end_date,b.total_amount,b.status FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.status='Pending' AND $range ORDER BY b.created_at DESC",'ss',[$from,$to]);break;
        case'completed':$rows=reportRows($db,"SELECT b.id,v.brand,v.model,v.plate_number,b.start_date,b.end_date,b.total_amount,b.status FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.status='Completed' AND $range ORDER BY b.created_at DESC",'ss',[$from,$to]);break;
        case'cancelled':$rows=reportRows($db,"SELECT b.id,v.brand,v.model,v.plate_number,b.start_date,b.end_date,b.total_amount,b.status FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.status='Cancelled' AND $range ORDER BY b.created_at DESC",'ss',[$from,$to]);break;
        default:$rows=reportRows($db,"SELECT b.id,v.brand,v.model,v.plate_number,b.start_date,b.end_date,b.total_amount,b.status FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE $range ORDER BY b.created_at DESC",'ss',[$from,$to]);
    }
}

if(($_GET['export']??'')==='csv'&&$db!==null){
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.str_replace(' ','-',strtolower($reports[$report])).'-'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fputcsv($out,[$reports[$report]]);fputcsv($out,['Generated',date('Y-m-d H:i:s')]);
    if(!in_array($report,['vehicle-status','returning-today','overdue'],true))fputcsv($out,['Date range',$from.' to '.$to]);
    fputcsv($out,[]);if($rows){fputcsv($out,array_keys($rows[0]));foreach($rows as $row)fputcsv($out,array_values($row));}else fputcsv($out,['No records found']);fclose($out);exit;
}
$totalRevenue=0;foreach($rows as $row)$totalRevenue+=(float)($row['revenue']??$row['total_amount']??0);
$showRange=!in_array($report,['vehicle-status','returning-today','overdue'],true);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reports | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.sidebar{background:#17202a;min-height:100vh}.sidebar a{color:#d5d8dc;text-decoration:none}.sidebar a:hover,.sidebar a.active{background:#273746;color:#fff}.report-menu .list-group-item.active{background:#17202a;border-color:#17202a}.table td,.table th{white-space:nowrap}@media print{.sidebar,.no-print{display:none!important}main{width:100%!important}.card{box-shadow:none!important}}</style></head><body><div class="container-fluid"><div class="row">
<aside class="col-md-3 col-lg-2 p-3 sidebar"><h4 class="text-white mb-4">Car Rental Admin</h4><nav class="nav flex-column gap-1"><a class="nav-link rounded" href="jeiven_dashboard.php">Dashboard</a><a class="nav-link rounded" href="jeiven_cars.php">Vehicles</a><a class="nav-link rounded" href="jeiven_add_car.php">Add Vehicle</a><a class="nav-link rounded active" href="jeiven_reports.php">Reports</a></nav><!-- Faith: same final sidebar/header style mo nalang dito para match lahat. --></aside>
<main class="col-md-9 col-lg-10 px-md-4 py-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="h3 mb-1">Reports</h1><p class="text-secondary mb-0">Fleet, booking, and revenue summaries</p></div><div class="no-print d-flex gap-2"><a class="btn btn-outline-success" href="?<?=e(http_build_query(['report'=>$report,'from'=>$from,'to'=>$to,'export'=>'csv']))?>">Export CSV</a><button class="btn btn-outline-dark" onclick="window.print()">Print</button></div></div>
<?php if($dbError):?><div class="alert alert-warning"><?=e($dbError)?></div><?php endif;?>
<div class="row g-4"><div class="col-lg-3 no-print"><div class="list-group report-menu shadow-sm"><?php foreach($reports as $key=>$label):?><a class="list-group-item list-group-item-action<?= $key===$report?' active':''?>" href="?<?=e(http_build_query(['report'=>$key,'from'=>$from,'to'=>$to]))?>"><?=e($label)?></a><?php endforeach;?></div></div>
<div class="col-lg-9"><div class="card border-0 shadow-sm"><div class="card-header bg-white py-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1"><?=e($reports[$report])?></h2><small class="text-secondary">Generated <?=e(date('M j, Y g:i A'))?> using server time</small></div><?php if($showRange):?><form class="row g-2 align-items-end no-print" method="get"><input type="hidden" name="report" value="<?=e($report)?>"><div class="col-auto"><label class="form-label small mb-1" for="from">From</label><input class="form-control form-control-sm" type="date" id="from" name="from" value="<?=e($from)?>" max="<?=e($to)?>"></div><div class="col-auto"><label class="form-label small mb-1" for="to">To</label><input class="form-control form-control-sm" type="date" id="to" name="to" value="<?=e($to)?>" min="<?=e($from)?>"></div><div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div></form><?php endif;?></div></div>
<div class="card-body border-bottom"><div class="row"><div class="col"><span class="text-secondary small">Records</span><div class="fs-4 fw-bold"><?=number_format(count($rows))?></div></div><?php if(in_array($report,['bookings','revenue','most-rented','completed'],true)):?><div class="col"><span class="text-secondary small"><?= $report==='most-rented'?'Completed Revenue':'Total Amount'?></span><div class="fs-4 fw-bold">₱<?=number_format($totalRevenue,2)?></div></div><?php endif;?></div></div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0">
<?php if($report==='vehicle-status'):?><thead class="table-light"><tr><th>Status</th><th class="text-end">Vehicles</th><th class="text-end">Combined Daily Rate</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="3" class="text-center text-secondary py-5">No vehicle data found.</td></tr><?php endif;?><?php foreach($rows as $row):?><tr><td><span class="badge text-bg-<?=reportBadge((string)$row['status'])?>"><?=e($row['status'])?></span></td><td class="text-end"><?=number_format((int)$row['total'])?></td><td class="text-end">₱<?=number_format((float)$row['combined_daily_rate'],2)?></td></tr><?php endforeach;?></tbody>
<?php elseif($report==='revenue'):?><thead class="table-light"><tr><th>Date</th><th class="text-end">Completed Bookings</th><th class="text-end">Revenue</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="3" class="text-center text-secondary py-5">No revenue records for this period.</td></tr><?php endif;?><?php foreach($rows as $row):?><tr><td><?=e(date('M j, Y',strtotime($row['report_date'])))?></td><td class="text-end"><?=number_format((int)$row['bookings'])?></td><td class="text-end">₱<?=number_format((float)$row['revenue'],2)?></td></tr><?php endforeach;?></tbody>
<?php elseif($report==='most-rented'):?><thead class="table-light"><tr><th>Vehicle</th><th>Plate</th><th class="text-end">Rentals</th><th class="text-end">Completed Revenue</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="4" class="text-center text-secondary py-5">No rental records for this period.</td></tr><?php endif;?><?php foreach($rows as $row):?><tr><td><a href="jeiven_edit_car.php?id=<?=(int)$row['id']?>" class="text-decoration-none fw-semibold"><?=e($row['brand'].' '.$row['model'])?></a></td><td><?=e($row['plate_number'])?></td><td class="text-end"><?=number_format((int)$row['rental_count'])?></td><td class="text-end">₱<?=number_format((float)$row['revenue'],2)?></td></tr><?php endforeach;?></tbody>
<?php else:?><thead class="table-light"><tr><th>Booking</th><th>Vehicle</th><th>Plate</th><th>Rental Period</th><?php if(in_array($report,['returning-today','overdue'],true)):?><th>Return Countdown</th><?php endif;?><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="<?=in_array($report,['returning-today','overdue'],true)?7:6?>" class="text-center text-secondary py-5">No records found for this report.</td></tr><?php endif;?><?php foreach($rows as $row):?><tr><td>#<?=(int)$row['id']?></td><td><strong><?=e($row['brand'].' '.$row['model'])?></strong></td><td><?=e($row['plate_number'])?></td><td><?=e(date('M j, Y',strtotime($row['start_date'])))?> – <?=e(date('M j, Y',strtotime($row['end_date'])))?></td><?php if(in_array($report,['returning-today','overdue'],true)):?><td class="fw-semibold <?=$report==='overdue'?'text-danger':'text-primary'?>"><?=e(officialDue((string)$row['end_date']))?></td><?php endif;?><td class="text-end">₱<?=number_format((float)$row['total_amount'],2)?></td><td><span class="badge text-bg-<?=reportBadge((string)$row['status'])?>"><?=e($row['status'])?></span></td></tr><?php endforeach;?></tbody><?php endif;?>
</table></div></div></div></div></main></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
