<?php
require __DIR__.'/gateway.php';
try{$live=ready();$enabled=$live||booking_open();}catch(Throwable $e){$live=$enabled=false;error_log('sanchari readiness unavailable class='.get_class($e).' reason='.substr($e->getMessage(),0,160));}
// test_pass only says whether this browser holds a valid test pass, so a tester can tell what is missing.
respond(['booking_enabled'=>$enabled,'test_mode'=>$enabled&&!$live,'test_pass'=>has_test_pass(),'timezone'=>'Asia/Kolkata']);
