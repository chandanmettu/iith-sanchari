<?php
// Shared helpers: config loading, raw cURL calls to the Razorpay REST API, JSON responses.
// No SDK/Composer — Hostinger shared hosting has neither guaranteed.

function rzp_config() {
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/config.php';
        if (!file_exists($path)) {
            json_out(['error' => 'Server not configured: copy api/config.sample.php to api/config.php'], 500);
        }
        $cfg = require $path;
    }
    return $cfg;
}

function rzp_curl($method, $endpoint, $body = null) {
    $cfg = rzp_config();
    $ch = curl_init('https://api.razorpay.com/v1/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_USERPWD        => $cfg['RAZORPAY_KEY_ID'] . ':' . $cfg['RAZORPAY_KEY_SECRET'],
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        return [0, ['error' => $err]];
    }
    return [$status, json_decode($res, true)];
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_in() {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}
