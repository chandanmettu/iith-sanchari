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

// Display-only strings for the ticket — not trusted for pricing, just carried
// through Razorpay's own order notes so we don't need a database yet.
$direction = substr((string)($input['direction'] ?? ''), 0, 10);
$routeDisplay = substr((string)($input['route_display'] ?? ''), 0, 60);
$departureDisplay = substr((string)($input['departure_display'] ?? ''), 0, 40);

[$status, $order] = rzp_curl('POST', 'orders', [
    'amount'   => $amountPaise,
    'currency' => 'INR',
    'receipt'  => 'sanchari_' . $route . '_' . time(),
    'notes'    => [
        'route' => $route, 'route_name' => $fares[$route]['name'],
        'direction' => $direction, 'route_display' => $routeDisplay, 'departure_display' => $departureDisplay,
    ],
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
