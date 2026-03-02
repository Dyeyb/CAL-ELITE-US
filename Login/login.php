<?php
/**
 * login.php
 * Authenticates a user by email + password against Supabase.
 * Returns JSON: { success: bool, message: string, data: { user_id, first_name, ... } }
 *
 * Expects POST JSON: { "email": "...", "password": "..." }
 */

define('SB_URL', 'https://pdqhbxtxvxrwtkvymjlm.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBkcWhieHR4dnhyd3RrdnltamxtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE1NTEyMzIsImV4cCI6MjA4NzEyNzIzMn0.jKq6Zw1XWDYXkxdrkW6HscOpsOuUm0gcyBCwFsAwN9U');
define('SB_TABLE', 'Users');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Supabase helper ───────────────────────────────────────────────────────────
function sb(string $method, string $endpoint, ?array $body = null): array
{
    $ch = curl_init(SB_URL . '/rest/v1/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . SB_KEY,
            'Authorization: Bearer ' . SB_KEY,
            'Prefer: return=representation',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body !== null)
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($raw, true), 'cerr' => $cerr];
}

function out(bool $ok, string $msg, $data = null, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    out(false, 'Method not allowed.', null, 405);

// ── Parse input ───────────────────────────────────────────────────────────────
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(trim($in['email'] ?? ''));
$password = $in['password'] ?? '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, 'Please enter a valid email address.', null, 422);
}
if (!$password) {
    out(false, 'Please enter your password.', null, 422);
}

// ── Fetch user from Supabase ──────────────────────────────────────────────────
$r = sb('GET', SB_TABLE
    . '?select=user_id,first_name,last_name,email,password_hash,user_type,status,is_archived,is_verified'
    . '&email=eq.' . urlencode($email)
    . '&limit=1');

if ($r['cerr'])
    out(false, 'Connection error. Please try again.', null, 500);
if ($r['status'] !== 200)
    out(false, 'Database error. Please try again.', null, 500);

if (empty($r['body']) || !is_array($r['body'])) {
    out(false, 'No account found with that email address.', null, 401);
}

$user = $r['body'][0];

// ── Account status checks ─────────────────────────────────────────────────────
if ((bool)($user['is_archived'] ?? false)) {
    out(false, 'This account has been archived. Please contact support.', null, 403);
}
if (($user['status'] ?? '') === 'inactive') {
    out(false, 'Your account is inactive. Please contact support.', null, 403);
}
if (($user['status'] ?? '') === 'suspended') {
    out(false, 'Your account has been suspended. Please contact support.', null, 403);
}

// ── Verify password ───────────────────────────────────────────────────────────
if (!password_verify($password, $user['password_hash'] ?? '')) {
    out(false, 'Incorrect password. Please try again.', null, 401);
}

// ── Determine redirect by role ────────────────────────────────────────────────
$redirect = match ($userType) {
        'admin' => '../admin-folders/admin_index.html',
        'service' => '../Service Panel/service-index.html',
        default => '../Homepage/index.html',
    };
// ── Return safe user data (never expose password_hash) ───────────────────────
out(true, 'Login successful.', [
    'user_id' => $user['user_id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'user_type' => $user['user_type'],
    'status' => $user['status'],
    'is_verified' => $user['is_verified'] ?? false,
    'redirect' => $redirect,
]);