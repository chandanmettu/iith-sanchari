<?php
require __DIR__.'/gateway.php';
try{$live=ready();$enabled=$live||booking_open();}catch(Throwable $e){$live=$enabled=false;error_log('sanchari readiness unavailable class='.get_class($e).' reason='.substr($e->getMessage(),0,160));}
// test_pass only says whether this browser holds a valid test pass, so a tester can tell what is missing.
$seen=isset($_COOKIE['sanchari_test']);$pass=has_test_pass();
if($seen){$why='';try{$why=ready(true)?'ready':'not_ready';}catch(Throwable $e){$why='error:'.get_class($e);}test_trace('status',['cookie'=>$seen,'pass'=>$pass,'enabled'=>$enabled,'test_ready'=>$why]);}
respond(['test_cookie_seen'=>$seen,'booking_enabled'=>$enabled,'test_mode'=>$enabled&&!$live,'test_pass'=>$pass,'timezone'=>'Asia/Kolkata']);
