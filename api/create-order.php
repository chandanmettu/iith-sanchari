<?php
require __DIR__ . '/razorpay.php';

$input = json_in();
$route = $input['route'] ?? '';
$fares = require __DIR__ . '/fares.php';

if (!isset($fares[$route])) {
    json_out(['error' => 'Unknown route'], 400);
}

$amountPaise = $fares[$route]['fare'] * 100;
if ($amountPaise < 100) {
    json_out(['error' => 'Amount below Razorpay minimum'], 400);
}

[$status, $order] = rzp_curl('POST', 'orders', [
    'amount'   => $amountPaise,
    'currency' => 'INR',
    'receipt'  => 'sanchari_' . $route . '_' . time(),
    'notes'    => ['route' => $route, 'route_name' => $fares[$route]['name']],
]);

if ($status === 401) {
    json_out(['error' => 'Razorpay auth failed — check api/config.php key/secret'], 401);
}
if ($status !== 200 || empty($order['id'])) {
    json_out(['error' => 'Could not create order', 'detail' => $order], 500);
}

$cfg = rzp_config();
json_out([
    'order_id'   => $order['id'],
    'amount'     => $order['amount'],
    'currency'   => $order['currency'],
    'key_id'     => $cfg['RAZORPAY_KEY_ID'],
    'route_name' => $fares[$route]['name'],
]);
