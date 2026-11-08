<?php
require __DIR__.'/gateway.php';
// Turns the private test pass on or off for this browser. Only works while test keys are configured.
$in=input();$value=test_pass_value();
if(!$value)respond(['error'=>'Test mode is not available.'],404);
if(($in['action']??'')==='off'){
    setcookie('sanchari_test','',['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    respond(['test_mode'=>false]);
}
$key=$in['key']??'';
if(!is_string($key)||strlen($key)>200||!hash_equals(test_mode_key(),$key)){test_trace('pass_rejected',['len'=>is_string($key)?strlen($key):-1]);limit_requests('test-pass-failed',10,300);respond(['error'=>'Test pass not accepted.'],401);}
test_trace('pass_accepted');
setcookie('sanchari_test',$value,['expires'=>time()+7*86400,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
respond(['test_mode'=>true]);
