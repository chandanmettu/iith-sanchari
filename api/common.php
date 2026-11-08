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
// With no DB_DSN, bookings live in a SQLite file that creates itself: outside the web root
// when the host allows it, otherwise in api/data/ behind a deny-all .htaccess.
function data_dir() {
    // A subdomain can sit inside another site's public_html, so never go "outside" into a public folder.
    $parent=realpath(dirname(__DIR__,2));$outside=$parent.'/sanchari-data';
    if ($parent && !str_contains($parent,'public_html') && (is_dir($outside) || (is_writable($parent) && @mkdir($outside,0700)))) return $outside;
    $inside=__DIR__.'/data';
    if (!is_dir($inside) && !@mkdir($inside,0700)) throw new RuntimeException('Storage unavailable');
    if (!is_file($inside.'/.htaccess')) file_put_contents($inside.'/.htaccess',"Require all denied\n");
    return $inside;
}
function sqlite_schema($db) {
    if ((int)$db->query('PRAGMA user_version')->fetchColumn()>=1) return;
    $db->exec("CREATE TABLE IF NOT EXISTS bookings (id TEXT PRIMARY KEY, recovery_hash TEXT NOT NULL, route TEXT NOT NULL, direction TEXT NOT NULL,
        departure INTEGER NOT NULL, valid_from INTEGER NOT NULL, valid_until INTEGER NOT NULL, amount INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'creating',
        provider_order TEXT NULL, payment_id TEXT UNIQUE NULL, checkout_url TEXT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL,
        last_checked INTEGER NOT NULL DEFAULT 0, used_at INTEGER NULL, used_by TEXT NULL);
        CREATE INDEX IF NOT EXISTS reconcile ON bookings (status, last_checked);
        CREATE INDEX IF NOT EXISTS departures ON bookings (departure);
        CREATE TABLE IF NOT EXISTS rate_limits (bucket TEXT PRIMARY KEY, hits INTEGER NOT NULL DEFAULT 0, expires_at INTEGER NOT NULL);
        CREATE INDEX IF NOT EXISTS expiry ON rate_limits (expires_at);
        CREATE TABLE IF NOT EXISTS operations (id TEXT PRIMARY KEY, booking_id TEXT NOT NULL, action TEXT NOT NULL, actor TEXT NOT NULL, created_at INTEGER NOT NULL);
        PRAGMA user_version=1;");
}
function db() {
    static $db;
    if (!$db) {
        $c=config(); $dsn=$c['DB_DSN']??'';
        if ($dsn==='') $dsn='sqlite:'.data_dir().'/sanchari.sqlite';
        if (!str_starts_with($dsn,'mysql:') && !str_starts_with($dsn,'sqlite:')) throw new RuntimeException('Database unavailable');
        $db=new PDO($dsn,$c['DB_USER']??null,$c['DB_PASSWORD']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') { $db->exec('PRAGMA busy_timeout=5000'); $db->exec('PRAGMA journal_mode=WAL'); sqlite_schema($db); }
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
        'used_at'=>$row['used_at']?(int)$row['used_at']:null,'boarding'=>scheduled_boarding($cfg,$row['direction'],$d),
        'boarding_url'=>$cfg['boarding_'.$row['direction'].'_url']??null];
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
