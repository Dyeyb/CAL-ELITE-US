<?php
// ─── Supabase config ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../../Login/db-config.php';

// ─── CORS + JSON headers ──────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Only accept POST ─────────────────────────────────────────────────────────
if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ─── Read and parse JSON body ─────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$in = json_decode($raw, true);

if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

// ─── Required field validation ────────────────────────────────────────────────
$fullName = trim($in['full_name'] ?? '');
$email = trim($in['email'] ?? '');
$address = trim($in['address'] ?? '');

if ($fullName === '' || $address === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Full name and service address are required.']);
    exit;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit;
}

// ─── Map service_assessment (must be 'urgent', 'planning', 'emergency' or null)
$assessmentRaw = strtolower(trim($in['service_assessment'] ?? ''));
$allowedAssessment = ['urgent', 'planning', 'emergency'];
$serviceAssessment = in_array($assessmentRaw, $allowedAssessment, true) ? $assessmentRaw : null;

// ─── Map preferred_date (Y-m-d) ───────────────────────────────────────────────
$dateRaw = trim($in['preferred_date'] ?? '');
$prefDate = null;
if ($dateRaw !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $dateRaw);
    if ($d && $d->format('Y-m-d') === $dateRaw) {
        $prefDate = $dateRaw;
    }
}

// ─── Map preferred_time (H:i) ─────────────────────────────────────────────────
// Frontend provides text value (Morning, Afternoon, Evening). Map to approx time.
$timeRaw = strtolower(trim($in['preferred_time'] ?? ''));
$prefTime = null;
if ($timeRaw === 'morning')
    $prefTime = '08:00:00';
if ($timeRaw === 'afternoon')
    $prefTime = '13:00:00';
if ($timeRaw === 'evening')
    $prefTime = '18:00:00';
// If it's already a valid time string (HH:MM), use it directly
if (!$prefTime && preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $timeRaw)) {
    $prefTime = $timeRaw;
}

// ─── Build service_inquiry payload ───────────────────────────────────────────
$payload = [
    // Assessment
    'service_assessment' => $serviceAssessment,

    // Contact info
    'full_name' => $fullName,
    'email' => $email,
    'address' => $address,
    'contact_number' => trim($in['contact_number'] ?? '') ?: null,

    // Schedule
    'preferred_date' => $prefDate,
    'preferred_time' => $prefTime,
    'estimated_duration' => trim($in['estimated_duration'] ?? '') ?: null,

    // Notes & metadata
    'notes' => trim($in['notes'] ?? '') ?: null,
    'service_type' => trim($in['service_type'] ?? '') ?: 'service_inquiry',
    'status' => 'pending',
];

// ─── Insert into service_inquiry ──────────────────────────────────────────────
$r = supabase_request('POST', 'service_inquiry', $payload);

if ($r['status'] === 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Connection error: ' . ($r['body']['error'] ?? 'Unknown cURL error.')
    ]);
    exit;
}

if (!in_array($r['status'], [200, 201], true)) {
    $body = $r['body'];
    $msg = is_array($body) ? ($body['message'] ?? $body['hint'] ?? null) : null;
    if (!$msg)
        $msg = 'Supabase error (HTTP ' . $r['status'] . ').';

    $rawLower = strtolower((string) json_encode($body));
    if (str_contains($rawLower, 'rls') || str_contains($rawLower, 'security policy') || in_array($r['status'], [401, 403])) {
        $msg = 'RLS is blocking inserts. Run in Supabase SQL Editor: ALTER TABLE "service_inquiry" DISABLE ROW LEVEL SECURITY;';
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $msg, 'raw' => $r['body']]);
    exit;
}

$row = is_array($r['body']) ? ($r['body'][0] ?? $r['body']) : $r['body'];

http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => 'Service inquiry submitted successfully.',
    'data' => $row,
]);
