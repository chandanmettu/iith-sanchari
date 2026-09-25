<?php
require __DIR__.'/hosted.php';
$in=input();if(!ready())respond(['error'=>'Online booking is not open yet.'],503);
limit_requests('create',max(1,(int)(config()['ORDER_RATE_PER_MIN']??60)),60);
$id=field($in,'id','/^s_[a-f0-9]{32}$/D');$key=field($in,'recovery_key','/^[a-f0-9]{48}$/D');
$route=field($in,'route','/^(patan|miya)$/D');$direction=field($in,'direction','/^(from|to)$/D');
$departure=$in['departure']??null;
if(!is_int($departure))respond(['error'=>'Select a scheduled departure.'],400);
if($existing=booking($id)){
    if(!hash_equals($existing['recovery_hash'],hash('sha256',$key))||$existing['route']!==$route||$existing['direction']!==$direction||(int)$existing['departure']!==$departure)respond(['error'=>'Booking details do not match.'],409);
    if(in_array($existing['status'],['paid','used']))respond(booking_response($existing));
    if($existing['status']==='pending'&&$existing['checkout_url']&&$departure>time()+360)respond(['id'=>$id,'checkout_url'=>$existing['checkout_url']]);
    // Never create another provider order after an ambiguous timeout.
    respond(['error'=>'This booking already exists. Open payment recovery to check its status.'],409);
}
$data=route_data();$cfg=$data['routes'][$route];$d=(new DateTimeImmutable('@'.$departure))->setTimezone(new DateTimeZone('Asia/Kolkata'));
if($departure<time()+360||$departure>time()+14*86400||!in_array($d->format('H:i'),$cfg[$direction],true)||$d->format('s')!=='00'||($cfg['weekdays']&&(int)$d->format('N')>5)||in_array($d->format('Y-m-d'),$data['holidays'],true))respond(['error'=>'That departure is not available. Please select another journey.'],400);
$c=config();
try{query('INSERT INTO bookings (id,recovery_hash,route,direction,departure,valid_from,valid_until,amount,status,created_at,updated_at,last_checked) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)',[$id,hash('sha256',$key),$route,$direction,$departure,$departure-$c['BOARDING_BEFORE_MIN']*60,$departure+$c['BOARDING_AFTER_MIN']*60,$cfg['fare']*100,'creating',time(),time()]);}
catch(PDOException $e){if(in_array((string)$e->getCode(),['23000','23505']))respond(['error'=>'Booking is already being created. Open payment recovery.'],409);throw $e;}
$row=gateway_create(booking($id),$key);respond(['id'=>$id,'checkout_url'=>$row['checkout_url']]);
