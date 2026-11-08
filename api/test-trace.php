<?php
require __DIR__.'/gateway.php';
// Temporary (2026-11-08): last 40 test-mode trace lines. Event names and yes/no flags only.
$f=data_dir().'/test-trace.log';$lines=is_file($f)?array_slice(file($f,FILE_IGNORE_NEW_LINES),-40):[];
respond(['trace'=>array_map(fn($l)=>json_decode($l,true),$lines)]);
