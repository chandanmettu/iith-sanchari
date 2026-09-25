<?php
// Application helpers. No payment authority or secrets live in browser code.
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
function respond($data, $status = 200) {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
set_exception_handler(function ($e) {
    $ref = bin2hex(random_bytes(6));
    // Intentionally exclude requests, credentials, provider bodies and PDO messages.
    error_log('sanchari failure ref=' . $ref . ' class=' . get_class($e));
    respond(['error'=>'Service temporarily unavailable. Keep your booking reference and try again.', 'reference'=>$ref],503);
});
function config() {
    static $cfg;
    if ($cfg === null) {
        $path = getenv('SANCHARI_CONFIG') ?: __DIR__ . '/config.php';
        $cfg = is_file($path) ? require $path : [];
        if (!is_array($cfg)) $cfg = [];
    }
    return $cfg;
}
function input() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); respond(['error'=>'Use POST.'],405); }
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) respond(['error'=>'JSON body required.'],415);
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw)>16384) respond(['error'=>'Request too large.'],413);
    $data = json_decode($raw,true);
    if (!is_array($data) || array_is_list($data)) respond(['error'=>'Invalid request.'],400);
    return $data;
}
function field($data,$key,$pattern) {
    $value = $data[$key] ?? null;
    if (!is_string($value) || !preg_match($pattern,$value)) respond(['error'=>'Invalid ' . str_replace('_',' ',$key) . '.'],400);
    return $value;
}
function db() {
    static $db;
    if (!$db) {
        $c=config(); $dsn=$c['DB_DSN']??'';
        if (!str_starts_with($dsn,'mysql:') && !(($c['APP_ENV']??'')==='test' && str_starts_with($dsn,'sqlite:'))) throw new RuntimeException('Database unavailable');
        $db=new PDO($dsn,$c['DB_USER']??null,$c['DB_PASSWORD']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $db->exec('PRAGMA busy_timeout=5000');
    }
    return $db;
}
function query($sql,$values=[]) { $q=db()->prepare($sql);$q->execute($values);return $q; }
function route_data() { static $data;return $data??=json_decode(file_get_contents(__DIR__.'/../assets/routes.json'),true,512,JSON_THROW_ON_ERROR); }
function scheduled_times($cfg,$direction,$departure) {
    $weekend=(int)$departure->format('N')>5;
    if(isset($cfg['timetable']))return $cfg['timetable'][$weekend?'weekend':'weekday'][$direction]??[];
    return $weekend&&($cfg['weekdays']??false)?[]:($cfg[$direction]??[]);
}
function scheduled_boarding($cfg,$direction,$departure) {
    if((int)$departure->format('N')>5&&isset($cfg['boarding_'.$direction.'_weekend']))return $cfg['boarding_'.$direction.'_weekend'];
    return $cfg['boarding_'.$direction];
}
function signing_key() { $key=config()['TICKET_HMAC_SECRET']??'';if(strlen($key)<32)throw new RuntimeException('Signing unavailable');return $key; }
function limit_requests($scope,$limit=20,$seconds=60) {
    $ip=$_SERVER['REMOTE_ADDR']??'local';
    $bucket=hash_hmac('sha256',$scope.'|'.$ip.'|'.intdiv(time(),$seconds),signing_key());
    try { query('INSERT INTO rate_limits (bucket,hits,expires_at) VALUES (?,0,?)',[$bucket,time()+$seconds*2]); }
    catch(PDOException $e){ if(!in_array((string)$e->getCode(),['23000','23505']))throw $e; }
    $q=query('UPDATE rate_limits SET hits=hits+1 WHERE bucket=? AND hits<?',[$bucket,$limit]);
    if($q->rowCount()!==1){header('Retry-After: '.$seconds);respond(['error'=>'Too many requests. Please wait a minute.'],429);}
}
function booking($id) { return query('SELECT * FROM bookings WHERE id=?',[$id])->fetch() ?: null; }
function authenticated_booking($in) {
    $id=field($in,'id','/^s_[a-f0-9]{32}$/D');$key=field($in,'recovery_key','/^[a-f0-9]{48}$/D');
    $row=booking($id);
    if(!$row||!hash_equals($row['recovery_hash'],hash('sha256',$key)))respond(['error'=>'Booking not found. Use the original recovery link.'],404);
    return $row;
}
function token_for($row) { $payload='v2.'.$row['id'];return $payload.'.'.hash_hmac('sha256',$payload,signing_key()); }
function row_for_token($token) {
    if(!is_string($token)||!preg_match('/^v2\.(s_[a-f0-9]{32})\.([a-f0-9]{64})$/D',$token,$m))return null;
    if(!hash_equals(hash_hmac('sha256','v2.'.$m[1],signing_key()),$m[2]))return null;
    return booking($m[1]);
}
function ticket_details($row) {
    $cfg=route_data()['routes'][$row['route']];
    $d=(new DateTimeImmutable('@'.$row['departure']))->setTimezone(new DateTimeZone('Asia/Kolkata'));
    return ['id'=>$row['id'],'route_display'=>$row['direction']==='from'?'IITH → '.$cfg['name']:$cfg['name'].' → IITH',
        'route'=>$row['route'],'direction'=>$row['direction'],'departure'=>(int)$row['departure'],
        'departure_display'=>$d->format('g:i A'),'arrival_display'=>$d->modify('+'.$cfg['journey_mins'].' minutes')->format('D, g:i A'),
        'journey_display'=>$cfg['journey_mins'].' min','fare'=>$row['amount']/100,'payment_id'=>$row['payment_id'],
        'issued_at'=>(int)$row['created_at'],'valid_from'=>(int)$row['valid_from'],'valid_until'=>(int)$row['valid_until'],
        'used_at'=>$row['used_at']?(int)$row['used_at']:null,'boarding'=>scheduled_boarding($cfg,$row['direction'],$d)];
}
function ticket_state($row) {
    if(!$row)return 'invalid';
    if($row['status']==='used')return 'used';
    if($row['status']!=='paid')return $row['status'];
    if(time()>(int)$row['valid_until'])return 'expired';
    if(time()<(int)$row['valid_from'])return 'upcoming';
    return 'valid';
}
function booking_response($row) {
    $result=['id'=>$row['id'],'status'=>$row['status'],'boarding_status'=>ticket_state($row)];
    if(in_array($row['status'],['paid','used'])){$result['token']=token_for($row);$result['ticket']=ticket_details($row);}
    return $result;
}
function staff_auth() {
    $id=$_SERVER['HTTP_X_STAFF_ID']??'';$key=$_SERVER['HTTP_X_STAFF_KEY']??'';
    $staff=config()['STAFF']??[];$entry=$staff[$id]??[];
    if(!$entry||!is_string($key)||strlen($key)>200||!password_verify($key,$entry['password_hash']??'')){limit_requests('staff-auth-failed',10,300);respond(['error'=>'Staff sign-in was not accepted.'],401);}
    limit_requests('staff-requests-'.$id,120,60);
    return ['id'=>$id,'role'=>$entry['role']??'driver'];
}
