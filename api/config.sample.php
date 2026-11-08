<?php
// Copy to server-only config.php, or build it with tools/make-config.py (recommended).
// Never put live values, account documents or database exports in this repository.
return [
    'APP_ENV' => 'production',
    'BOOKING_ENABLED' => false,      // Public launch switch.
    'OPERATIONS_CONFIRMED' => false, // Transport has confirmed fares, timetable and operator details.
    'ORDER_RATE_PER_MIN' => 60, // Per direct client IP; check campus NAT/proxy behavior in pilot.
    'PUBLIC_URL' => 'https://sanchari.iith.online',
    'DB_DSN' => '', // Blank: a SQLite file the server creates by itself. Or mysql:host=...;dbname=...;charset=utf8mb4 with api/schema.sql.
    'DB_USER' => '',
    'DB_PASSWORD' => '',
    'TICKET_HMAC_SECRET' => '', // At least 32 random bytes; openssl rand -hex 32. Change it when moving from test to live.
    'PAYMENT_PROTOCOL' => 'hosted-links-v1', // or oauth-checkout-v2 (older adapter in hosted.php).
    'PAYMENT_ENV' => 'test', // test or live. Only test allows the private test pass.
    'PAYMENT_BASE_URL' => '', // API base URL for that environment, from the provider's docs.
    'PAYMENT_API_VERSION' => '2025-01-01',
    'PAYMENT_CLIENT_ID' => '',
    'PAYMENT_CLIENT_SECRET' => '',
    'CHECKOUT_HOSTS' => [], // Exact allowed checkout page hosts, no wildcards.
    'TEST_MODE_KEY' => '', // Test environment only: opening /#test=<key> lets that browser book with test money.
    'BOARDING_BEFORE_MIN' => null, // Transport must approve both boarding-window values before launch.
    'BOARDING_AFTER_MIN' => null,
    'STAFF' => [
        // 'driver-01' => ['password_hash' => password_hash result, 'role' => 'driver'],
        // 'office-01' => ['password_hash' => password_hash result, 'role' => 'admin'],
    ],
];
