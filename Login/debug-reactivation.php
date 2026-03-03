<?php
/**
 * Network Error Diagnostic
 * 
 * Identifies exactly where the "network error" is coming from
 */

header('Content-Type: application/json');
require_once './db-config.php';

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'test_email' => 'mitra_johnbernard@plpasig.edu.ph',
    'network_tests' => [],
    'api_calls' => [],
    'issues' => []
];

// ─────────────────────────────────────────────────────────────────────
// Test 1: Check PHP network capabilities
// ─────────────────────────────────────────────────────────────────────

$debug['network_tests'][] = [
    'test' => 'PHP Network Extensions',
    'curl_enabled' => extension_loaded('curl'),
    'fsockopen_enabled' => function_exists('fsockopen'),
    'stream_context_create' => function_exists('stream_context_create'),
];

// ─────────────────────────────────────────────────────────────────────
// Test 2: Test basic cURL connectivity
// ─────────────────────────────────────────────────────────────────────

$curl_test = [
    'url' => SUPABASE_URL . '/rest/v1/Users?select=count()',
    'method' => 'GET',
    'headers' => [
        'Content-Type: application/json',
        'apikey: ' . substr(SUPABASE_ANON_KEY, 0, 20) . '...',
        'Authorization: Bearer ' . substr(SUPABASE_ANON_KEY, 0, 20) . '...',
    ]
];

$ch = curl_init($curl_test['url']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => $curl_test['headers'],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => fopen('php://temp', 'w+'),
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

$debug['network_tests'][] = [
    'test' => 'Basic Supabase Connection',
    'url' => $curl_test['url'],
    'http_code' => $http_code,
    'curl_error' => $curl_error,
    'curl_errno' => $curl_errno,
    'success' => $curl_errno === 0
];

// ─────────────────────────────────────────────────────────────────────
// Test 3: Test full send-reactivation-otp.php endpoint
// ─────────────────────────────────────────────────────────────────────

$test_payload = json_encode(['email' => 'mitra_johnbernard@plpasig.edu.ph']);

$ch = curl_init('http://localhost/Mrs.Alu/Login/send-reactivation-otp.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $test_payload,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

$debug['api_calls'][] = [
    'endpoint' => 'send-reactivation-otp.php',
    'method' => 'POST',
    'payload' => ['email' => 'mitra_johnbernard@plpasig.edu.ph'],
    'http_code' => $http_code,
    'response' => json_decode($response, true),
    'curl_error' => $curl_error,
    'curl_errno' => $curl_errno,
    'success' => $curl_errno === 0 && $http_code === 200
];

// ─────────────────────────────────────────────────────────────────────
// Test 4: Check if send-reactivation-otp.php exists and is readable
// ─────────────────────────────────────────────────────────────────────

$otp_file = __DIR__ . '/send-reactivation-otp.php';
$debug['file_checks'][] = [
    'file' => 'send-reactivation-otp.php',
    'exists' => file_exists($otp_file),
    'readable' => is_readable($otp_file),
    'size' => file_exists($otp_file) ? filesize($otp_file) : 0,
    'path' => $otp_file
];

// ─────────────────────────────────────────────────────────────────────
// Test 5: Check error logs
// ─────────────────────────────────────────────────────────────────────

$log_file = __DIR__ . '/logs/app.log';
$recent_logs = [];
if (file_exists($log_file)) {
    $lines = file($log_file);
    $recent_logs = array_slice($lines, -20); // Last 20 lines
}

$debug['logs'] = [
    'log_file_exists' => file_exists($log_file),
    'log_file_path' => $log_file,
    'recent_entries' => $recent_logs
];

// ─────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────

$network_ok = true;
$api_ok = true;

foreach ($debug['network_tests'] as $test) {
    if (isset($test['success']) && !$test['success']) {
        $network_ok = false;
        $debug['issues'][] = 'Network issue: ' . ($test['curl_error'] ?? 'Unknown');
    }
}

foreach ($debug['api_calls'] as $call) {
    if (!$call['success']) {
        $api_ok = false;
        $debug['issues'][] = 'API call failed: ' . $call['endpoint'] . ' (HTTP ' . $call['http_code'] . ', errno: ' . $call['curl_errno'] . ')';
    }
}

$debug['summary'] = [
    'network_ok' => $network_ok,
    'api_ok' => $api_ok,
    'file_ok' => isset($debug['file_checks'][0]['exists']) && $debug['file_checks'][0]['exists'],
    'total_issues' => count($debug['issues']),
    'issues' => $debug['issues']
];

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>