<?php
require_once __DIR__.'/common.php';
// Loads the adapter for the configured payment protocol and decides who may book.
function payment_protocol() { return config()['PAYMENT_PROTOCOL']??''; }
require_once __DIR__.(payment_protocol()==='hosted-links-v1'?'/links.php':'/hosted.php');

// Test mode: with test-environment keys, a browser holding the private test pass can book with
// test money while the public still sees booking closed. Live keys never allow it.
function test_mode_key() {
    $c=config();$key=$c['TEST_MODE_KEY']??'';
    return ($c['PAYMENT_ENV']??'')==='test'&&strlen($key)>=32?$key:null;
}
function test_pass_value() { $key=test_mode_key();return $key?hash_hmac('sha256','sanchari-test-pass',$key):null; }
function has_test_pass() {
    $value=test_pass_value();$cookie=$_COOKIE['sanchari_test']??'';
    return $value&&is_string($cookie)&&hash_equals($value,$cookie);
}
function booking_open() {
    if(ready())return true;
    return has_test_pass()&&ready(true);
}
