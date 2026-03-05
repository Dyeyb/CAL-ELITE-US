<?php
require_once __DIR__ . '/common.php';

services_require_method(['POST', 'OPTIONS']);

$input = services_get_input();
[$payload, $errors] = services_validate_payload($input, false);
if (!empty($errors)) {
    json_response(false, $errors[0], ['errors' => $errors], 422);
}

$payload['is_archived'] = false;
$payload['prev_status'] = null;
$payload['archived_at'] = null;

$response = supabase_request('POST', SUPABASE_SERVICES_TABLE, $payload);
if (in_array($response['status'], [200, 201], true)) {
    $created = is_array($response['body']) ? ($response['body'][0] ?? $response['body']) : $response['body'];
    json_response(true, 'Service created successfully.', $created, 201);
}

services_handle_supabase_error($response, 'Failed to create service.');

