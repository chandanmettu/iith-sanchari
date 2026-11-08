<?php
require_once __DIR__.'/common.php';
// Hosted payment-link protocol ("hosted-links-v1"). The API base URL, keys, API version and the
// allowed checkout hosts are server-only configuration. Each booking id is also the link id, so a
// retried create returns the same link and every status check is keyed on our own reference.
function link_http($path,$method='GET',$body=null,$idempotency=null) {
    $c=config();$url=rtrim($c['PAYMENT_BASE_URL']??'','/').$path;
    if(parse_url($url,PHP_URL_SCHEME)!=='https' && !(($c['APP_ENV']??'')==='test' && parse_url($url,PHP_URL_HOST)==='127.0.0.1'))throw new RuntimeException('Secure URL required');
    $headers=['Accept: application/json','Content-Type: application/json','x-api-version: '.($c['PAYMENT_API_VERSION']??'2025-01-01'),
        'x-client-id: '.($c['PAYMENT_CLIENT_ID']??''),'x-client-secret: '.($c['PAYMENT_CLIENT_SECRET']??'')];
    if($idempotency)$headers[]='x-idempotency-key: '.$idempotency;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false]);
    if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_SLASHES));
    $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($raw===false)throw new RuntimeException('Gateway request failed');
    return [$code,json_decode($raw,true)];
}
function link_ok($path,$method='GET',$body=null,$idempotency=null) {
    [$code,$data]=link_http($path,$method,$body,$idempotency);
    if($code<200||$code>=300||!is_array($data)){
        $why=['http'=>$code,'path'=>strtok($path,'?'),'code'=>substr((string)($data['code']??''),0,60),'message'=>substr((string)($data['message']??''),0,200)];
        error_log('sanchari gateway '.json_encode($why));if(function_exists('test_trace'))test_trace('gateway_error',$why);
        throw new RuntimeException('Gateway request failed');}
    return $data;
}
function checkout_allowed($url) {
    $host=parse_url((string)$url,PHP_URL_HOST);
    if(parse_url((string)$url,PHP_URL_SCHEME)==='https'&&in_array($host,config()['CHECKOUT_HOSTS']??[],true))return true;
    error_log('sanchari checkout host not allowed host='.$host);if(function_exists('test_trace'))test_trace('checkout_host_rejected',['host'=>(string)$host]);return false;
}
function ist_time($ts) { return (new DateTimeImmutable('@'.$ts))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d\TH:i:sP'); }
function gateway_create($row,$recoveryKey,$phone=null) {
    $c=config();$seconds=min(1200,(int)$row['departure']-time()-60);
    if($seconds<300)throw new RuntimeException('Departure too close');
    if(!is_string($phone)||!preg_match('/^[6-9][0-9]{9}$/D',$phone))throw new RuntimeException('Phone required');
    $cfg=route_data()['routes'][$row['route']];$base=rtrim($c['PUBLIC_URL'],'/');
    $result=link_ok('/links','POST',[
        'link_id'=>$row['id'],'link_amount'=>$row['amount']/100,'link_currency'=>'INR',
        'link_purpose'=>'Sanchari bus journey, '.($row['direction']==='from'?'IITH to '.$cfg['name']:$cfg['name'].' to IITH'),
        'customer_details'=>['customer_phone'=>$phone],'link_partial_payments'=>false,'link_expiry_time'=>ist_time(time()+$seconds),
        'link_notify'=>['send_sms'=>false,'send_email'=>false],'link_auto_reminders'=>false,'link_notes'=>['booking'=>$row['id']],
        'link_meta'=>['return_url'=>$base.'/payment.html#id='.$row['id'].'&key='.$recoveryKey,'notify_url'=>$base.'/api/webhook.php'],
    ],$row['id']);
    if(($result['link_id']??'')!==$row['id']||!checkout_allowed($result['link_url']??''))throw new RuntimeException('Checkout unavailable');
    query("UPDATE bookings SET provider_order=?,checkout_url=?,status='pending',updated_at=? WHERE id=? AND status='creating'",[(string)($result['cf_link_id']??$row['id']),$result['link_url'],time(),$row['id']]);
    return booking($row['id']);
}
// Status always comes from the gateway API, never from a browser or a notification body.
function apply_link_status($row) {
    $path='/links/'.rawurlencode($row['id']);
    [$code,$orders]=link_http($path.'/orders?status=PAID');
    if($code===404)return link_missing($row);
    if($code<200||$code>=300||!is_array($orders))throw new RuntimeException('Gateway request failed');
    foreach((array_is_list($orders)?$orders:($orders['data']??[]))as$o){
        if(($o['order_status']??'')!=='PAID'||(int)round((float)($o['order_amount']??0)*100)!==(int)$row['amount'])continue;
        // Once used, revoked or refunded, no later check may make the ticket boardable again.
        query("UPDATE bookings SET status='paid',payment_id=?,updated_at=? WHERE id=? AND status IN ('creating','pending','failed')",[(string)($o['cf_order_id']??$o['order_id']??$row['id']),time(),$row['id']]);
        return booking($row['id']);
    }
    [$code,$link]=link_http($path);
    if($code===404)return link_missing($row);
    if($code<200||$code>=300||!is_array($link))throw new RuntimeException('Gateway request failed');
    $state=$link['link_status']??'';
    if(in_array($state,['EXPIRED','CANCELLED'],true)){
        query("UPDATE bookings SET status='failed',updated_at=? WHERE id=? AND status IN ('creating','pending')",[time(),$row['id']]);
    }elseif($state==='ACTIVE'&&$row['status']==='creating'&&($link['link_id']??'')===$row['id']&&checkout_allowed($link['link_url']??'')){
        query("UPDATE bookings SET status='pending',provider_order=?,checkout_url=?,updated_at=? WHERE id=? AND status='creating'",[(string)($link['cf_link_id']??$row['id']),$link['link_url'],time(),$row['id']]);
    }
    return booking($row['id']);
}
// No link exists, so nothing can be paid on it. Give an in-flight create two minutes first.
function link_missing($row) {
    if($row['status']==='creating'&&(int)$row['created_at']<time()-120)query("UPDATE bookings SET status='failed',updated_at=? WHERE id=? AND status='creating'",[time(),$row['id']]);
    return booking($row['id']);
}
function reconcile($row,$force=false) {
    if(!in_array($row['status'],['creating','pending','failed'])||(!$force&&(int)$row['last_checked']>time()-15))return $row;
    query('UPDATE bookings SET last_checked=? WHERE id=?',[time(),$row['id']]);
    return apply_link_status($row);
}
// $test skips only the public launch switches; keys, staff, boarding window and storage must still be ready.
function ready($test=false) {
    $c=config();
    foreach(['TICKET_HMAC_SECRET','PAYMENT_CLIENT_ID','PAYMENT_CLIENT_SECRET','PAYMENT_BASE_URL','PUBLIC_URL','CHECKOUT_HOSTS','STAFF']as$key)if(empty($c[$key]))return false;
    if(!$test&&(empty($c['BOOKING_ENABLED'])||empty($c['OPERATIONS_CONFIRMED'])||empty(route_data()['confirmed'])))return false;
    if(!isset($c['BOARDING_BEFORE_MIN'],$c['BOARDING_AFTER_MIN'])||$c['BOARDING_BEFORE_MIN']<0||$c['BOARDING_AFTER_MIN']<1)return false;
    signing_key();query('SELECT id FROM bookings LIMIT 1');query('SELECT bucket FROM rate_limits LIMIT 1');query('SELECT id FROM operations LIMIT 1');return true;
}
// A notification is only a prompt to re-check: find our booking reference in it and ask the API.
function notification_booking_id($raw) {
    return preg_match('/s_[a-f0-9]{32}/',$raw,$m)?$m[0]:null;
}
