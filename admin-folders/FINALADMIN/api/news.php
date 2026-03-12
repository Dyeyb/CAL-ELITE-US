<?php
// ─── Supabase config ──────────────────────────────────────────────────────────
define('SB_URL', 'https://pdqhbxtxvxrwtkvymjlm.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBkcWhieHR4dnhyd3RrdnltamxtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE1NTEyMzIsImV4cCI6MjA4NzEyNzIzMn0.jKq6Zw1XWDYXkxdrkW6HscOpsOuUm0gcyBCwFsAwN9U');
define('SB_NEWS', 'news');

// ─── CORS + JSON ──────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── cURL helper (same as products.php) ──────────────────────────────────────
function sb_news(string $method, string $endpoint, ?array $body = null): array
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
$newsId = trim($_GET['id'] ?? '');

// ─── GET — fetch all news ─────────────────────────────────────────────────────
if ($method === 'GET') {
    $r = sb_news('GET', SB_NEWS . '?select=*&order=published_at.desc');

    if ($r['cerr'])
        out(false, 'cURL error: ' . $r['cerr'], null, 500);

    if ($r['status'] === 200)
        out(true, 'News fetched successfully.', $r['body']);

    // RLS check
    $raw = strtolower($r['raw'] ?? '');
    if (str_contains($raw, 'rls') || str_contains($raw, 'security policy') || in_array($r['status'], [401, 403]))
        out(false, 'RLS is blocking reads. Run in Supabase SQL Editor: ALTER TABLE news DISABLE ROW LEVEL SECURITY;', ['http' => $r['status'], 'raw' => $r['raw']], 403);

    out(false, $r['body']['message'] ?? ('Supabase error HTTP ' . $r['status']), ['raw' => $r['raw']], 500);
}

// ─── POST — insert new article ────────────────────────────────────────────────
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];

    $title = trim($in['title'] ?? '');
    $excerpt = trim($in['excerpt'] ?? '');
    $content = trim($in['content'] ?? '');
    $status = trim($in['status'] ?? 'draft');

    if (!$title)
        out(false, 'title is required.', null, 422);
    if (!$excerpt)
        out(false, 'excerpt is required.', null, 422);
    if (!$content)
        out(false, 'content is required.', null, 422);
    if (!in_array($status, ['published', 'draft', 'archived']))
        $status = 'draft';

    $payload = [
        'title' => $title,
        'excerpt' => $excerpt,
        'content' => $content,
        'thumbnail_url' => trim($in['thumbnail_url'] ?? '') ?: null,
        'author' => trim($in['author'] ?? '') ?: 'Admin',
        'status' => $status,
        'is_archived' => false,
        'published_at' => date('c'),
        'updated_at' => date('c'),
    ];

    $r = sb_news('POST', SB_NEWS, $payload);

    if ($r['cerr'])
        out(false, 'cURL error: ' . $r['cerr'], null, 500);

    if (in_array($r['status'], [200, 201])) {
        $row = is_array($r['body']) ? ($r['body'][0] ?? $r['body']) : $r['body'];
        out(true, 'Article created successfully.', $row, 201);
    }

    $raw = strtolower($r['raw'] ?? '');
    if (str_contains($raw, 'rls') || str_contains($raw, 'security policy') || in_array($r['status'], [401, 403]))
        out(false, 'RLS is blocking inserts. Run in Supabase SQL Editor: ALTER TABLE news DISABLE ROW LEVEL SECURITY;', ['http' => $r['status'], 'raw' => $r['raw']], 403);

    out(false, $r['body']['message'] ?? ('Insert failed. HTTP ' . $r['status']), ['raw' => $r['raw']], 500);
}

// ─── PUT — update article ─────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$newsId)
        out(false, 'id is required.', null, 400);

    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $payload = [];

    $allowed = ['title', 'excerpt', 'content', 'thumbnail_url', 'author', 'status', 'is_archived', 'prev_status', 'archived_at'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $in))
            $payload[$f] = $in[$f];
    }

    if (empty($payload))
        out(false, 'No fields to update.', null, 422);

    $payload['updated_at'] = date('c');

    // auto-stamp archived_at
    if (isset($payload['is_archived']) && $payload['is_archived'] === true && empty($payload['archived_at']))
        $payload['archived_at'] = date('c');

    // clear archive fields on restore
    if (isset($payload['is_archived']) && $payload['is_archived'] === false) {
        $payload['archived_at'] = null;
        $payload['prev_status'] = null;
    }

    $r = sb_news('PATCH', SB_NEWS . '?id=eq.' . urlencode($newsId), $payload);

    if ($r['cerr'])
        out(false, 'cURL error: ' . $r['cerr'], null, 500);

    if (in_array($r['status'], [200, 204])) {
        $row = is_array($r['body']) ? ($r['body'][0] ?? null) : null;
        out(true, 'Article updated successfully.', $row);
    }

    out(false, $r['body']['message'] ?? ('Update failed. HTTP ' . $r['status']), ['raw' => $r['raw']], 500);
}

// ─── DELETE — hard delete ─────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$newsId)
        out(false, 'id is required.', null, 400);

    $r = sb_news('DELETE', SB_NEWS . '?id=eq.' . urlencode($newsId));

    if ($r['cerr'])
        out(false, 'cURL error: ' . $r['cerr'], null, 500);

    if (in_array($r['status'], [200, 204]))
        out(true, 'Article deleted permanently.');

    out(false, $r['body']['message'] ?? ('Delete failed. HTTP ' . $r['status']), ['raw' => $r['raw']], 500);
}

out(false, 'Method not allowed.', null, 405);
