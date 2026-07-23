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

// Signature checks out — payment is genuine. Ticket issuance (DB row, signed QR)
// plugs in here once the MySQL schema is set up; for now this just confirms payment.
json_out(['verified' => true, 'payment_id' => $paymentId, 'order_id' => $orderId]);
