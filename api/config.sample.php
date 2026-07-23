<?php
// Copy this file to config.php (git-ignored) and fill in real values.
// Never commit config.php — it holds live secrets.
return [
    'RAZORPAY_KEY_ID'     => '',
    'RAZORPAY_KEY_SECRET' => '',
    // Random 64-char string, only used to sign/verify ticket QR tokens — unrelated to Razorpay.
    // Generate one with: openssl rand -hex 32
    'TICKET_HMAC_SECRET'  => '',
];
