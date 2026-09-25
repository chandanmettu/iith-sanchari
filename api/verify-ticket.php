<?php
require __DIR__.'/common.php';
$in=input();limit_requests('verify',120,60);$row=row_for_token($in['token']??'');$state=ticket_state($row);
if(!$row)respond(['valid'=>false,'reason'=>'invalid'],400);
respond(['valid'=>in_array($state,['valid','upcoming']),'reason'=>$state,'ticket'=>ticket_details($row)]);
