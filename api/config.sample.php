<?php
// Copy to server-only config.php. Preserve the existing file in a private backup.
// Never put live values, account documents or database exports in this repository.
return [
    'APP_ENV' => 'production',
    'BOOKING_ENABLED' => false,
    'OPERATIONS_CONFIRMED' => false,
    'ORDER_RATE_PER_MIN' => 60, // Per direct client IP; check campus NAT/proxy behavior in pilot.
    'PUBLIC_URL' => 'https://sanchari.iith.online',
    'DB_DSN' => '', // mysql:host=localhost;dbname=DATABASE;charset=utf8mb4
    'DB_USER' => '',
    'DB_PASSWORD' => '',
    'TICKET_HMAC_SECRET' => '', // At least 32 random bytes; openssl rand -hex 32.
    'PAYMENT_PROTOCOL' => '', // oauth-checkout-v2 after account-specific UAT.
    'PAYMENT_CLIENT_ID' => '',
    'PAYMENT_CLIENT_SECRET' => '',
    'PAYMENT_CLIENT_VERSION' => '',
    'PAYMENT_AUTH_URL' => '', // Exact environment URL from the approved provider.
    'PAYMENT_BASE_URL' => '', // Base before /checkout/v2/...
    'CHECKOUT_HOSTS' => [], // Exact allowed redirect hosts, no wildcards.
    'WEBHOOK_USERNAME' => '',
    'WEBHOOK_PASSWORD' => '', // Configure SHA authentication in the provider dashboard.
    'BOARDING_BEFORE_MIN' => null, // Transport must approve both boarding-window values.
    'BOARDING_AFTER_MIN' => null,
    'STAFF' => [
        // 'driver-01' => ['password_hash' => password_hash result, 'role' => 'driver'],
        // 'office-01' => ['password_hash' => password_hash result, 'role' => 'admin'],
    ],
];
