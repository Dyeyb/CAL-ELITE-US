<?php
require_once __DIR__ . '/../../Login/db-config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
  exit;
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);

if (!is_array($in)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
  exit;
}

// Map fields to table structure
$firstName = trim($in['first_name'] ?? '');
$lastName = trim($in['last_name'] ?? '');
$fullName = trim("$firstName $lastName");
$email = trim($in['email'] ?? '');
$phone = trim($in['phone'] ?? '') ?: null;
$subject = trim($in['subject'] ?? '');
$message = trim($in['message'] ?? '');
$rawType = strtolower(trim($in['type'] ?? ''));

// Validate required fields
if (!$fullName || !$email || !$subject || !$message) {
  echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
  exit;
}

// Strict mapping for inquiry_type (only General Inquiry or Feedback allowed in DB)
$inquiryType = 'General Inquiry';
if (in_array($rawType, ['complaint', 'feedback'])) {
  $inquiryType = 'Feedback';
}

$payload = [
  'full_name' => $fullName,
  'email' => $email,
  'phone' => $phone,
  'inquiry_type' => $inquiryType,
  'subject' => $subject,
  'message' => $message
];

$r = supabase_request('POST', 'inquiries', $payload);

if ($r['status'] === 0) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Connection error.']);
  exit;
}

if (!in_array($r['status'], [200, 201], true)) {
  // Check for RLS issues since the table is brand new
  $body = $r['body'];
  $rawLower = strtolower((string) json_encode($body));
  $msg = 'Failed to save inquiry.';

  if (str_contains($rawLower, 'rls') || str_contains($rawLower, 'security policy') || in_array($r['status'], [401, 403])) {
    $msg = 'RLS blocking insert. Run in Supabase SQL Editor: ALTER TABLE "inquiries" DISABLE ROW LEVEL SECURITY;';
  } else if (is_array($body) && isset($body['message'])) {
    $msg = $body['message'];
  }

  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $msg, 'raw' => $body]);
  exit;
}

http_response_code(201);
echo json_encode([
  'success' => true,
  'message' => 'Message sent successfully.'
]);
