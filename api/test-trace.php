<?php
require __DIR__.'/gateway.php';
// Temporary (2026-11-08): last 40 test-mode trace lines (event names and yes/no flags only).
// ?check=1 asks the payment API about a link that cannot exist, to confirm the keys and network
// work: a 404 means connected; anything else returns the API's own error code and message.
if(isset($_GET['check'])&&payment_protocol()==='hosted-links-v1'&&test_mode_key()){
    limit_requests('trace-check',6,60);
    [$code,$data]=link_http('/links/s_'.str_repeat('0',32));
    respond(['http'=>$code,'code'=>substr((string)($data['code']??''),0,60),'message'=>substr((string)($data['message']??''),0,200),'type'=>substr((string)($data['type']??''),0,60)]);
}
$f=data_dir().'/test-trace.log';$lines=is_file($f)?array_slice(file($f,FILE_IGNORE_NEW_LINES),-40):[];
respond(['trace'=>array_map(fn($l)=>json_decode($l,true),$lines)]);
