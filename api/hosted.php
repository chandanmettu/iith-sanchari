<?php
require_once __DIR__.'/common.php';
// OAuth hosted-checkout protocol. Endpoint URLs are server-only configuration.
// This adapter is disabled by default and must pass the selected account's UAT.
function gateway_http($url,$method='GET',$body=null,$headers=[]) {
    $c=config();$scheme=parse_url($url,PHP_URL_SCHEME);
    if($scheme!=='https' && !(($c['APP_ENV']??'')==='test' && parse_url($url,PHP_URL_HOST)==='127.0.0.1'))throw new RuntimeException('Secure URL required');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false]);
    if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($raw===false||$code<200||$code>=300)throw new RuntimeException('Gateway request failed');
    $data=json_decode($raw,true);if(!is_array($data))throw new RuntimeException('Gateway response invalid');return $data;
}
function gateway_call($path,$method='GET',$body=null) {
    static $token;
    $c=config();if(($c['PAYMENT_PROTOCOL']??'')!=='oauth-checkout-v2')throw new RuntimeException('Adapter not selected');
    if(!$token){$auth=gateway_http($c['PAYMENT_AUTH_URL']??'','POST',http_build_query(['client_id'=>$c['PAYMENT_CLIENT_ID'],'client_version'=>$c['PAYMENT_CLIENT_VERSION'],'client_secret'=>$c['PAYMENT_CLIENT_SECRET'],'grant_type'=>'client_credentials']),['Content-Type: application/x-www-form-urlencoded']);$token=$auth['access_token']??null;if(!$token)throw new RuntimeException('Auth unavailable');}
    return gateway_http(rtrim($c['PAYMENT_BASE_URL'],'/').$path,$method,$body===null?null:json_encode($body),['Content-Type: application/json','Authorization: O-Bearer '.$token]);
}
function gateway_create($row,$recoveryKey) {
    $c=config();$seconds=min(1200,(int)$row['departure']-time()-60);
    if($seconds<300)throw new RuntimeException('Departure too close');
    $url=rtrim($c['PUBLIC_URL'],'/').'/payment.html#id='.$row['id'].'&key='.$recoveryKey;
    $result=gateway_call('/checkout/v2/pay','POST',['merchantOrderId'=>$row['id'],'amount'=>(int)$row['amount'],'expireAfter'=>$seconds,'paymentFlow'=>['type'=>'PG_CHECKOUT','message'=>'Sanchari bus journey','merchantUrls'=>['redirectUrl'=>$url]]]);
    $redirect=$result['redirectUrl']??'';$host=parse_url($redirect,PHP_URL_HOST);
    if(parse_url($redirect,PHP_URL_SCHEME)!=='https'||!in_array($host,$c['CHECKOUT_HOSTS']??[],true)||empty($result['orderId']))throw new RuntimeException('Checkout unavailable');
    query("UPDATE bookings SET provider_order=?,checkout_url=?,status='pending',updated_at=? WHERE id=? AND status='creating'",[$result['orderId'],$redirect,time(),$row['id']]);
    return booking($row['id']);
}
function apply_payment_status($row,$remote) {
    if(!empty($row['provider_order'])&&($remote['orderId']??'')!==$row['provider_order'])throw new RuntimeException('Order mismatch');
    if((int)($remote['amount']??0)!==(int)$row['amount'])throw new RuntimeException('Amount mismatch');
    if(($remote['state']??'')==='COMPLETED'){
        $paid=null;foreach(($remote['paymentDetails']??[])as$p){if(($p['state']??'')==='COMPLETED'&&(int)($p['amount']??0)===(int)$row['amount']&&!empty($p['transactionId'])){$paid=$p;break;}}
        if(!$paid)throw new RuntimeException('Payment not final');
        // Once used, revoked, or refunded, no callback may make the ticket boardable again.
        query("UPDATE bookings SET status='paid',payment_id=?,provider_order=?,updated_at=? WHERE id=? AND status IN ('creating','pending','failed')",[$paid['transactionId'],$remote['orderId'],time(),$row['id']]);
    }elseif(($remote['state']??'')==='FAILED'){
        query("UPDATE bookings SET status='failed',updated_at=? WHERE id=? AND status IN ('creating','pending')",[time(),$row['id']]);
    }
    return booking($row['id']);
}
function reconcile($row,$force=false) {
    if(!in_array($row['status'],['creating','pending','failed'])||(!$force&&(int)$row['last_checked']>time()-15))return $row;
    query('UPDATE bookings SET last_checked=? WHERE id=?',[time(),$row['id']]);
    $remote=gateway_call('/checkout/v2/order/'.rawurlencode($row['id']).'/status?details=true');
    return apply_payment_status($row,$remote);
}
function ready() {
    $c=config();
    foreach(['BOOKING_ENABLED','OPERATIONS_CONFIRMED','DB_DSN','TICKET_HMAC_SECRET','PAYMENT_CLIENT_ID','PAYMENT_CLIENT_SECRET','PAYMENT_CLIENT_VERSION','PAYMENT_BASE_URL','PAYMENT_AUTH_URL','PUBLIC_URL','CHECKOUT_HOSTS','WEBHOOK_USERNAME','WEBHOOK_PASSWORD','STAFF']as$key)if(empty($c[$key]))return false;
    if(($c['PAYMENT_PROTOCOL']??'')!=='oauth-checkout-v2'||empty(route_data()['confirmed']))return false;
    if(!isset($c['BOARDING_BEFORE_MIN'],$c['BOARDING_AFTER_MIN'])||$c['BOARDING_BEFORE_MIN']<0||$c['BOARDING_AFTER_MIN']<1)return false;
    signing_key();query('SELECT id FROM bookings LIMIT 1');query('SELECT bucket FROM rate_limits LIMIT 1');query('SELECT id FROM operations LIMIT 1');return true;
}
