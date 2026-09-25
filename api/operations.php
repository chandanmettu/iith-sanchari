<?php
require __DIR__.'/hosted.php';
$in=input();$staff=staff_auth();if($staff['role']!=='admin')respond(['error'=>'Administrator access required.'],403);
$action=$in['action']??'list';
if($action==='list'){
    $rows=query('SELECT id,route,direction,departure,amount,status,payment_id,created_at,used_at FROM bookings ORDER BY created_at DESC LIMIT 100')->fetchAll();
    respond(['bookings'=>$rows]);
}
$id=field($in,'id','/^s_[a-f0-9]{32}$/D');$row=booking($id);if(!$row)respond(['error'=>'Booking not found.'],404);
if($action==='reconcile')respond(booking_response(reconcile($row,true)));
if($action==='revoke'){
    $db=db();$db->beginTransaction();query("UPDATE bookings SET status='revoked',updated_at=? WHERE id=? AND status IN ('paid','pending','creating','failed')",[time(),$id]);
    query('INSERT INTO operations (id,booking_id,action,actor,created_at) VALUES (?,?,?,?,?)',[bin2hex(random_bytes(16)),$id,'revoke',$staff['id'],time()]);$db->commit();respond(['revoked'=>true]);
}
respond(['error'=>'Unknown action.'],400);
