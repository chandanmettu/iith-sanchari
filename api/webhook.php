<?php
require __DIR__.'/hosted.php';
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
