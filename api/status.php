<?php
require __DIR__.'/hosted.php';
try{$enabled=ready();}catch(Throwable $e){$enabled=false;error_log('sanchari readiness unavailable');}
respond(['booking_enabled'=>$enabled,'timezone'=>'Asia/Kolkata']);
