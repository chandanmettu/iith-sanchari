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

// Direction is a genuine user choice, but only these two values exist.
$direction = ($input['direction'] ?? '') === 'to' ? 'to' : 'from';

// Route name and duration are derived here, never taken from the browser —
// otherwise someone could pay the ₹30 fare while making the ticket print the
// ₹100 route. Everything the ticket asserts about *what was bought* is decided
// server-side; the client only picks a route id and a direction.
$name = $fares[$route]['name'];
$routeDisplay = $direction === 'from' ? "IITH → {$name}" : "{$name} → IITH";
$mins = $fares[$route]['journey_mins'];
$journeyDisplay = $mins < 60
    ? $mins . ' min'
    : intdiv($mins, 60) . ' hr' . ($mins % 60 ? ' ' . ($mins % 60) . ' min' : '');

// Departure/arrival are schedule-derived clock strings from the client. They're
// informational only (the fare and route above are what's enforced), so they're
// length-capped rather than recomputed — moving them server-side would mean
// duplicating the whole timetable out of assets/app.js. Worth doing once the DB
// lands and schedules live in a table instead of a JS constant.
$departureDisplay = substr((string)($input['departure_display'] ?? ''), 0, 40);
$arrivalDisplay = substr((string)($input['arrival_display'] ?? ''), 0, 40);

[$status, $order] = rzp_curl('POST', 'orders', [
    'amount'   => $amountPaise,
    'currency' => 'INR',
    'receipt'  => 'sanchari_' . $route . '_' . time(),
    'notes'    => [
        'route' => $route, 'route_name' => $fares[$route]['name'],
        'direction' => $direction, 'route_display' => $routeDisplay, 'departure_display' => $departureDisplay,
        'arrival_display' => $arrivalDisplay, 'journey_display' => $journeyDisplay,
    ],
]);

// Error text stays generic on purpose: the gateway's raw response can carry
// account/config detail that shouldn't reach a browser. Log server-side, tell
// the user only what they can act on.
if ($status === 401) {
    error_log('sanchari: gateway auth rejected on order create');
    json_out(['error' => 'Payments are temporarily unavailable. Please try again later.'], 503);
}
if ($status !== 200 || empty($order['id'])) {
    error_log('sanchari: order create failed (http ' . $status . ') ' . json_encode($order));
    json_out(['error' => 'Could not start payment. Please try again.'], 502);
}

$cfg = rzp_config();
json_out([
    'order_id'   => $order['id'],
    'amount'     => $order['amount'],
    'currency'   => $order['currency'],
    'key_id'     => $cfg['RAZORPAY_KEY_ID'],
    'route_name' => $fares[$route]['name'],
]);
