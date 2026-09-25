<?php
require __DIR__.'/common.php';
$in=input();$staff=staff_auth();$row=row_for_token($in['token']??'');
if(!$row)respond(['error'=>'Ticket not recognised.'],400);
if(($in['route']??'')!==$row['route']||($in['direction']??'')!==$row['direction']||(int)($in['departure']??0)!==(int)$row['departure'])respond(['error'=>'This ticket is for a different journey.'],409);
$state=ticket_state($row);if($state!=='valid')respond(['error'=>'Boarding unavailable: '.$state.'.'],409);
if(($in['confirm']??false)!==true)respond(['valid'=>true,'ticket'=>ticket_details($row)]);
$db=db();$db->beginTransaction();
$q=query("UPDATE bookings SET status='used',used_at=?,used_by=?,updated_at=? WHERE id=? AND status='paid' AND used_at IS NULL AND valid_from<=? AND valid_until>=?",[time(),$staff['id'],time(),$row['id'],time(),time()]);
if($q->rowCount()!==1){$db->rollBack();respond(['error'=>'Ticket was already used or is no longer valid.'],409);}
query('INSERT INTO operations (id,booking_id,action,actor,created_at) VALUES (?,?,?,?,?)',[bin2hex(random_bytes(16)),$row['id'],'check_in',$staff['id'],time()]);$db->commit();
respond(['checked_in'=>true,'ticket'=>ticket_details(booking($row['id']))]);
