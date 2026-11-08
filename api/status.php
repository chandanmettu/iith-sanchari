<?php
require __DIR__.'/gateway.php';
try{$live=ready();$enabled=$live||booking_open();}catch(Throwable $e){$live=$enabled=false;error_log('sanchari readiness unavailable class='.get_class($e).' reason='.substr($e->getMessage(),0,160));}
respond(['booking_enabled'=>$enabled,'test_mode'=>$enabled&&!$live,'timezone'=>'Asia/Kolkata']);
