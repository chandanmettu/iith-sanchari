<?php
// Verifies a ticket token's HMAC signature server-side.
//
// Why this exists: ticket.html can decode the token's payload on its own, but it
// can never check the signature — that needs TICKET_HMAC_SECRET, which must stay
// on the server. Without this endpoint a forged payload renders as a genuine
// ticket, so the "Verified / Valid" badges are gated on this response.
//
// Also the seam the future driver-scan screen verifies against: it scans the QR
// (which encodes this same token) and calls here.
require __DIR__ . '/razorpay.php';

$input = json_in();
$token = (string)($input['token'] ?? '');

// Expected shape: <base64url payload>.<hex hmac>
$parts = explode('.', $token);
if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
    json_out(['valid' => false, 'reason' => 'malformed'], 400);
}
[$payloadB64, $sig] = $parts;

$cfg = rzp_config();
$expected = hash_hmac('sha256', $payloadB64, $cfg['TICKET_HMAC_SECRET']);

// hash_equals: constant-time, so a wrong signature can't be brute-forced by
// timing how long the comparison takes.
if (!hash_equals($expected, $sig)) {
    json_out(['valid' => false, 'reason' => 'bad_signature'], 400);
}

$json = base64_decode(strtr($payloadB64, '-_', '+/'), true);
$ticket = $json === false ? null : json_decode($json, true);
if (!is_array($ticket)) {
    json_out(['valid' => false, 'reason' => 'undecodable'], 400);
}

// Signature is genuine, so the payload is exactly what we issued.
//
// NOT checked here, and deliberately so — both need the database that isn't
// provisioned yet (see docs/PROGRESS.md):
//   - single-use: nothing records that a ticket was already used to board
//   - expiry: issued_at is returned below, but no validity window is enforced
// Until then a genuine ticket is replayable. The driver-scan screen closes this.
json_out(['valid' => true, 'ticket' => $ticket]);
