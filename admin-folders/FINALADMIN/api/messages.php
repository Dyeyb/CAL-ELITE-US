<?php
require_once __DIR__ . '/db-config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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
    return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw, 'cerr' => $cerr];
}

function out(bool $ok, string $msg, $data = null, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD']);

if ($method === 'GET') {
    // 1. Fetch from inquiries
    $resInq = supabase_request('GET', 'inquiries?select=*');
    $inquiries = ($resInq['status'] === 200 && is_array($resInq['body'])) ? $resInq['body'] : [];
    foreach ($inquiries as &$m) {
        $m['_source'] = 'inquiries';
        $m['type'] = $m['inquiry_type'] ?? 'inquiry';
        $m['sender_name'] = $m['full_name'] ?? 'Unknown';
        $m['sender_email'] = $m['email'] ?? 'Unknown';
        $m['sender_phone'] = $m['phone'] ?? '';
    }

    // 2. Fetch from product_inquiry
    $resProd = supabase_request('GET', 'product_inquiry?select=*');
    $prodInqs = ($resProd['status'] === 200 && is_array($resProd['body'])) ? $resProd['body'] : [];
    foreach ($prodInqs as &$m) {
        $m['_source'] = 'product_inquiry';
        $m['type'] = 'quotation';
        $m['sender_name'] = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
        $m['sender_email'] = $m['email'] ?? 'Unknown';
        $m['sender_phone'] = $m['phone'] ?? '';
        $m['subject'] = 'Product Quote: ' . ($m['product_name'] ?? 'Multiple Items');
        $m['message'] = "Quantity: " . ($m['quantity'] ?? 1) . "\nNotes: " . ($m['order_notes'] ?? 'None');
    }

    // 3. Fetch from service_inquiry
    $resSvc = supabase_request('GET', 'service_inquiry?select=*');
    $svcInqs = ($resSvc['status'] === 200 && is_array($resSvc['body'])) ? $resSvc['body'] : [];
    foreach ($svcInqs as &$m) {
        $m['_source'] = 'service_inquiry';
        $m['type'] = 'inquiry';
        $m['sender_name'] = $m['full_name'] ?? 'Unknown';
        $m['sender_email'] = $m['email'] ?? 'Unknown';
        $m['sender_phone'] = $m['contact_number'] ?? '';
        $m['subject'] = 'Service Booking: ' . ($m['service_type'] ?? 'General');
        $m['message'] = "Assessment: " . ($m['service_assessment'] ?? 'None') . "\nPreferred Date/Time: " . ($m['preferred_date'] ?? '') . " " . ($m['preferred_time'] ?? '') . "\nDuration: " . ($m['estimated_duration'] ?? '') . "\nNotes: " . ($m['notes'] ?? 'None');
    }

    // Combined
    $all = array_merge($inquiries, $prodInqs, $svcInqs);

    // Filter out rows without IDs or basic dates (or standardize dates)
    foreach ($all as &$msg) {
        $msg['id'] = $msg['_source'] . '_' . ($msg['id'] ?? $msg['inquiry_id'] ?? $msg['service_id'] ?? uniqid());
        $msg['status'] = $msg['status'] ?? 'unread';
        $msg['is_starred'] = $msg['is_starred'] ?? false;
        $msg['is_archived'] = $msg['is_archived'] ?? false;
        // Standardize date field
        $msg['created_at'] = $msg['created_at'] ?? date('c');
    }

    // Sort by created_at DESC
    usort($all, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    out(true, 'Messages fetched successfully.', $all);
}

if ($method === 'PATCH') {
    $idParam = trim($_GET['id'] ?? '');
    if (!$idParam)
        out(false, 'Message ID is required.', null, 400);

    // ID is formatted as "source_table_ID" e.g., "inquiries_12" or "product_inquiry_45"
    // Find the last underscore
    $lastUnderscorePos = strrpos($idParam, '_');
    if ($lastUnderscorePos === false) {
        // Fallback for old messages
        $table = 'inquiries';
        $actualId = $idParam;
        $idColumn = 'id';
    } else {
        $table = substr($idParam, 0, $lastUnderscorePos);
        $actualId = substr($idParam, $lastUnderscorePos + 1);

        if ($table === 'product_inquiry') {
            $idColumn = 'inquiry_id';
        } else if ($table === 'service_inquiry') {
            $idColumn = 'service_id';
        } else {
            $table = 'inquiries'; // default fallback
            $idColumn = 'id';
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input))
        out(false, 'Invalid payload.', null, 400);

    $input['updated_at'] = date('c');

    $res = supabase_request('PATCH', $table . '?' . $idColumn . '=eq.' . urlencode($actualId), $input);
    if (in_array($res['status'], [200, 204])) {
        out(true, 'Message updated successfully.', is_array($res['body']) ? ($res['body'][0] ?? null) : null);
    }
    out(false, 'Failed to update message. Supabase response: ' . json_encode($res), null, 500);
}

if ($method === 'DELETE') {
    $idParam = trim($_GET['id'] ?? '');
    if (!$idParam)
        out(false, 'Message ID is required.', null, 400);

    $lastUnderscorePos = strrpos($idParam, '_');
    if ($lastUnderscorePos === false) {
        $table = 'inquiries';
        $actualId = $idParam;
        $idColumn = 'id';
    } else {
        $table = substr($idParam, 0, $lastUnderscorePos);
        $actualId = substr($idParam, $lastUnderscorePos + 1);

        if ($table === 'product_inquiry') {
            $idColumn = 'inquiry_id';
        } else if ($table === 'service_inquiry') {
            $idColumn = 'service_id';
        } else {
            $table = 'inquiries';
            $idColumn = 'id';
        }
    }

    $res = supabase_request('DELETE', $table . '?' . $idColumn . '=eq.' . urlencode($actualId));
    if (in_array($res['status'], [200, 204])) {
        out(true, 'Message permanently deleted.');
    }
    out(false, 'Failed to delete message.', null, 500);
}

out(false, 'Method not allowed.', null, 405);
