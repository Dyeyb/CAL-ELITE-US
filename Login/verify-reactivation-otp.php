<?php
/**
 * verify-reactivation-otp.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Validates the 6-digit reactivation OTP and reactivates the user account.
 *
 * Expects POST JSON: { "email": "...", "otp": "123456" }
 */

define('SB_URL',        'https://pdqhbxtxvxrwtkvymjlm.supabase.co');
define('SB_KEY',        'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBkcWhieHR4dnhyd3RrdnltamxtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE1NTEyMzIsImV4cCI6MjA4NzEyNzIzMn0.jKq6Zw1XWDYXkxdrkW6HscOpsOuUm0gcyBCwFsAwN9U');
define('OTP_TABLE',     'OTP_Verifications');
define('USERS_TABLE',   'Users');
define('OTP_TYPE',      'Account Reactivation');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Helpers ───────────────────────────────────────────────────────────────────
function sb(string $method, string $endpoint, ?array $body = null): array {
    $ch = curl_init(SB_URL . '/rest/v1/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: '               . SB_KEY,
            'Authorization: Bearer ' . SB_KEY,
            'Prefer: return=representation',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr   = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($raw, true), 'cerr' => $cerr];
}

function out(bool $ok, string $msg, $data = null, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Method not allowed.', null, 405);

// ── Parse & validate input ────────────────────────────────────────────────────
$in    = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(trim($in['email'] ?? ''));
$otp   = trim($in['otp'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, 'Please provide a valid email address.', null, 422);
}
if (strlen($otp) !== 6 || !ctype_digit($otp)) {
    out(false, 'Please enter a valid 6-digit OTP.', null, 422);
}

// ── 1. Fetch the user ──────────────────────────────────────────────────────────
$ur = sb('GET', USERS_TABLE
    . '?email=eq.'  . urlencode($email)
    . '&select=user_id,first_name,last_name,status,is_archived'
    . '&limit=1');

if ($ur['cerr'])           out(false, 'Connection error. Please try again.', null, 500);
if ($ur['status'] !== 200) out(false, 'Database error. Please try again.',   null, 500);
if (empty($ur['body']))    out(false, 'Account not found.',                   null, 404);

$user   = $ur['body'][0];
$userId = $user['user_id'];

// ── 2. Fetch latest valid unused OTP for this email + type ────────────────────
// Build URL manually (NO double-encoding — this is the fix)
$now = gmdate('Y-m-d\TH:i:s') . '+00:00';

$r = sb('GET', OTP_TABLE
    . '?email=eq.'       . urlencode($email)
    . '&type=eq.'        . urlencode(OTP_TYPE)
    . '&used=eq.false'
    . '&expires_at=gte.' . urlencode($now)
    . '&order=created_at.desc&limit=1'
);

if ($r['cerr'])  out(false, 'Connection error. Please try again.',                     null, 500);
if (empty($r['body']) || !is_array($r['body']) || count($r['body']) === 0) {
    out(false, 'OTP has expired or was already used. Please request a new one.',       null, 400);
}

$record = $r['body'][0];

// ── 3. Verify OTP against stored hash ─────────────────────────────────────────
if (!password_verify($otp, $record['otp_hash'] ?? '')) {
    out(false, 'Incorrect OTP. Please try again.', null, 400);
}

// ── 4. Mark OTP as used ───────────────────────────────────────────────────────
sb('PATCH', OTP_TABLE . '?id=eq.' . urlencode($record['id']), ['used' => true]);

// ── 5. Reactivate the user account ────────────────────────────────────────────
$upd = sb('PATCH', USERS_TABLE . '?user_id=eq.' . urlencode($userId), [
    'status'      => 'active',
    'is_archived' => false,
]);

if (!in_array($upd['status'], [200, 204])) {
    out(false, 'OTP verified but failed to reactivate account. Please contact support.', null, 500);
}

// ── 6. Return success ─────────────────────────────────────────────────────────
out(true, 'Account reactivated successfully! You can now log in.', [
    'user_id'    => $userId,
    'first_name' => $user['first_name'] ?? '',
    'last_name'  => $user['last_name']  ?? '',
    'email'      => $email,
    'status'     => 'active',
    'is_archived'=> false,
]);