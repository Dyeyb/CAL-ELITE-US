<?php
define('SB_URL', 'https://pdqhbxtxvxrwtkvymjlm.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBkcWhieHR4dnhyd3RrdnltamxtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE1NTEyMzIsImV4cCI6MjA4NzEyNzIzMn0.jKq6Zw1XWDYXkxdrkW6HscOpsOuUm0gcyBCwFsAwN9U');
define('SB_TABLE', 'Messages');

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
    $res = sb('GET', SB_TABLE . '?order=created_at.desc');

    if ($res['status'] === 200 && is_array($res['body'])) {
        out(true, 'Messages fetched successfully.', $res['body']);
    } else {
        out(false, 'Failed to fetch messages.', null, 500);
    }
}

if ($method === 'PATCH') {
    $id = trim($_GET['id'] ?? '');
    if (!$id)
        out(false, 'Message ID is required.', null, 400);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input))
        out(false, 'Invalid payload.', null, 400);

    $input['updated_at'] = date('c');

    $res = sb('PATCH', SB_TABLE . '?id=eq.' . urlencode($id), $input);
    if (in_array($res['status'], [200, 204])) {
        out(true, 'Message updated successfully.', is_array($res['body']) ? ($res['body'][0] ?? null) : null);
    }
    out(false, 'Failed to update message.', null, 500);
}

if ($method === 'DELETE') {
    $id = trim($_GET['id'] ?? '');
    if (!$id)
        out(false, 'Message ID is required.', null, 400);

    $res = sb('DELETE', SB_TABLE . '?id=eq.' . urlencode($id));
    if (in_array($res['status'], [200, 204])) {
        out(true, 'Message permanently deleted.');
    }
    out(false, 'Failed to delete message.', null, 500);
}

out(false, 'Method not allowed.', null, 405);
