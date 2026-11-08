<?php
require __DIR__.'/gateway.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');respond(['error'=>'Use POST.'],405);}
if(payment_protocol()==='hosted-links-v1'){
    // Format-agnostic: the body is only searched for our booking reference, then the API is asked.
    $raw=file_get_contents('php://input',false,null,0,65537);
    if(strlen($raw)>65536)respond(['error'=>'Request too large.'],413);
    limit_requests('webhook',120,60);
    $id=notification_booking_id($raw);$row=$id?booking($id):null;
    if($row)reconcile($row,true); // A failure answers 503, so the gateway retries later.
    respond(['received'=>true]);
}
$in=input();$c=config();
if(empty($c['WEBHOOK_USERNAME'])||empty($c['WEBHOOK_PASSWORD']))respond(['error'=>'Unavailable'],503);
$actual=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
if(!hash_equals(hash('sha256',$c['WEBHOOK_USERNAME'].':'.$c['WEBHOOK_PASSWORD']),$actual))respond(['error'=>'Not authorised'],401);
$p=$in['payload']??$in;
$id=$p['merchantOrderId']??$p['originalMerchantOrderId']??'';
if(!is_string($id)||!preg_match('/^s_[a-f0-9]{32}$/D',$id))respond(['received'=>true]);
$row=booking($id);if(!$row)respond(['received'=>true]);
if(isset($p['originalMerchantOrderId'])){
    // Revocation is conservative: partial refunds also require operator review.
    if(($p['state']??'')==='COMPLETED')query("UPDATE bookings SET status='refunded',updated_at=? WHERE id=?",[time(),$id]);
}else{reconcile($row,true);}
respond(['received'=>true]);
