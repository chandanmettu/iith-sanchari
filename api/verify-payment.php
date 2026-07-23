<?php
require __DIR__ . '/razorpay.php';

$input = json_in();
$orderId   = $input['razorpay_order_id']   ?? '';
$paymentId = $input['razorpay_payment_id'] ?? '';
$signature = $input['razorpay_signature']  ?? '';

if (!$orderId || !$paymentId || !$signature) {
    json_out(['error' => 'Missing fields'], 400);
}

$cfg = rzp_config();
$expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $cfg['RAZORPAY_KEY_SECRET']);

if (!hash_equals($expected, $signature)) {
    json_out(['verified' => false, 'error' => 'Signature mismatch'], 400);
}

// Signature checks out — payment is genuine. No database yet, so the ticket is a
// signed token (not a DB row): everything needed to render it travels in the token
// itself, pulled from the Razorpay payment record (phone) and its order notes
// (route/departure — round-tripped through create-order.php, see that file).
[$pStatus, $payment] = rzp_curl('GET', 'payments/' . urlencode($paymentId));
if ($pStatus !== 200 || empty($payment['id'])) {
    json_out(['verified' => true, 'payment_id' => $paymentId, 'order_id' => $orderId, 'ticket' => null]);
}

$notes = $payment['notes'] ?? [];
$ticket = [
    'route_name'         => $notes['route_name'] ?? '',
    'direction'          => $notes['direction'] ?? '',
    'route_display'      => $notes['route_display'] ?? '',
    'departure_display'  => $notes['departure_display'] ?? '',
    'arrival_display'    => $notes['arrival_display'] ?? '',
    'journey_display'    => $notes['journey_display'] ?? '',
    'fare'               => round(($payment['amount'] ?? 0) / 100),
    'contact'            => $payment['contact'] ?? '',
    'payment_id'         => $paymentId,
    'order_id'           => $orderId,
    'issued_at'          => time(),
];

$payloadJson = json_encode($ticket);
$payloadB64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
$ticketSig = hash_hmac('sha256', $payloadB64, $cfg['TICKET_HMAC_SECRET']);
$token = $payloadB64 . '.' . $ticketSig;

json_out(['verified' => true, 'payment_id' => $paymentId, 'order_id' => $orderId, 'ticket' => $ticket, 'token' => $token]);
