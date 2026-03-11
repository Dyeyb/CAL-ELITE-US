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

if ($fullName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit;
}

// ─── Product & quantity fields ────────────────────────────────────────────────
$productDbId = trim($in['product_db_id'] ?? '');   // UUID from products table
$productRef = trim($in['product_ref'] ?? '');   // e.g. "PRD-001"
$productName = trim($in['product_name'] ?? '');
$productSku = trim($in['product_sku'] ?? '');
$quantity = max(1, (int) ($in['quantity'] ?? 1));

// ─── Map installation_assistance (boolean / "Yes"/"No" string) ────────────────
$installRaw = $in['installation_assistance'] ?? null;
if (is_bool($installRaw)) {
    $installBool = $installRaw;
} elseif (is_string($installRaw)) {
    $installBool = strtolower(trim($installRaw)) === 'yes' ? true : false;
} else {
    $installBool = null;
}

// ─── Map project_type ─────────────────────────────────────────────────────────
$projectTypeRaw = strtolower(trim($in['project_type'] ?? ''));
$allowedProject = ['residential', 'commercial'];
$projectType = in_array($projectTypeRaw, $allowedProject, true) ? $projectTypeRaw : null;

// ─── Map customization_required (boolean / "Yes"/"No" string) ────────────────
$customRaw = $in['customization_required'] ?? null;
if (is_bool($customRaw)) {
    $customBool = $customRaw;
} elseif (is_string($customRaw)) {
    $customBool = strtolower(trim($customRaw)) === 'yes' ? true : false;
} else {
    $customBool = null;
}

// ─── Map preferred_time ───────────────────────────────────────────────────────
$timeRaw = strtolower(trim($in['preferred_time'] ?? ''));
$allowedTime = ['morning', 'afternoon', 'evening'];
$prefTime = in_array($timeRaw, $allowedTime, true) ? $timeRaw : null;

// ─── Map preferred_date (Y-m-d) ───────────────────────────────────────────────
$dateRaw = trim($in['preferred_date'] ?? '');
$prefDate = null;
if ($dateRaw !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $dateRaw);
    if ($d && $d->format('Y-m-d') === $dateRaw) {
        $prefDate = $dateRaw;
    }
}

// ─── Validate stock if product UUID was provided ──────────────────────────────
if ($productDbId !== '') {
    $stockR = supabase_request('GET', 'products', null, [
        'id' => 'eq.' . $productDbId,
        'select' => 'id,stocks,name',
        'limit' => '1',
    ]);

    if ($stockR['status'] === 200 && !empty($stockR['body'])) {
        $currentStocks = (int) ($stockR['body'][0]['stocks'] ?? 0);
        if ($currentStocks <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'This product is currently out of stock.']);
            exit;
        }
        if ($quantity > $currentStocks) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => "Requested quantity ($quantity) exceeds available stock ($currentStocks)."
            ]);
            exit;
        }
    }
}

// ─── Build product_inquiry payload ───────────────────────────────────────────
$payload = [
    // Product reference
    'product_db_id' => $productDbId ?: null,
    'product_ref' => $productRef ?: null,
    'product_name' => $productName ?: null,
    'product_sku' => $productSku ?: null,
    'quantity' => $quantity,

    // FAQ answers
    'installation_assistance' => $installBool,
    'project_type' => $projectType,
    'customization_required' => $customBool,

    // Contact info
    'full_name' => $fullName,
    'email' => $email,
    'contact_number' => trim($in['contact_number'] ?? '') ?: null,

    // Delivery
    'delivery_address' => trim($in['delivery_address'] ?? '') ?: null,

    // Schedule
    'preferred_date' => $prefDate,
    'preferred_time' => $prefTime,
    'estimated_duration' => trim($in['estimated_duration'] ?? '') ?: null,

    // Notes & metadata
    'additional_notes' => trim($in['additional_notes'] ?? '') ?: null,
    'inquiry_type' => 'product_inquiry',
    'status' => 'pending',
];

// ─── Insert into product_inquiry ──────────────────────────────────────────────
$r = supabase_request('POST', 'product_inquiry', $payload);

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
        $msg = 'RLS is blocking inserts. Run in Supabase SQL Editor: ALTER TABLE "product_inquiry" DISABLE ROW LEVEL SECURITY;';
    }
    // Column-not-found hint (before migration)
    if (str_contains($rawLower, 'column') && (str_contains($rawLower, 'product_db_id') || str_contains($rawLower, 'product_name') || str_contains($rawLower, 'quantity'))) {
        $msg = 'New columns not found. Run the ALTER TABLE migration SQL first.';
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $msg, 'raw' => $r['body']]);
    exit;
}

$row = is_array($r['body']) ? ($r['body'][0] ?? $r['body']) : $r['body'];

// ─── Decrement stock in products table ────────────────────────────────────────
if ($productDbId !== '') {
    $newStocks = max(0, $currentStocks - $quantity);
    supabase_request('PATCH', 'products', ['stocks' => $newStocks, 'updated_at' => date('c')], [
        'id' => 'eq.' . $productDbId,
    ]);
    // (errors here are non-fatal; inquiry is already saved)
}

http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => 'Inquiry submitted successfully.',
    'data' => $row,
]);
