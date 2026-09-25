<?php
// Hosting cron: php /absolute/path/api/reconcile.php — never expose a cron secret in a URL.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/hosted.php';
$failures=0;
foreach(query("SELECT * FROM bookings WHERE status IN ('creating','pending','failed') AND created_at>? AND last_checked<? ORDER BY last_checked LIMIT 50",[time()-7*86400,time()-60])->fetchAll()as$row){
    try{reconcile($row,true);}catch(Throwable $e){$failures++;error_log('sanchari reconciliation failed booking='.$row['id']);}
}
query('DELETE FROM rate_limits WHERE expires_at<?',[time()]);
echo json_encode(['failures'=>$failures,'checked_at'=>time()]).PHP_EOL;
exit($failures?1:0);
