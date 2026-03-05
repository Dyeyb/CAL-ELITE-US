<?php
require_once __DIR__ . '/common.php';

services_require_method(['PUT', 'PATCH', 'OPTIONS']);

$id = services_id_param();
if ($id === '') {
    json_response(false, 'Service id is required.', null, 400);
}

$existing = services_find_by_identifier($id);
if ($existing === null) {
    json_response(false, 'Service not found.', null, 404);
}

$input = services_get_input();
[$payload, $errors] = services_validate_payload($input, true);
if (!empty($errors)) {
    json_response(false, $errors[0], ['errors' => $errors], 422);
}

if (array_key_exists('is_archived', $payload)) {
    if ($payload['is_archived'] === true) {
        $payload['prev_status'] = $existing['status'] ?? 'active';
        $payload['status'] = 'archived';
        $payload['archived_at'] = date('c');
    } else {
        $payload['status'] = isset($payload['status']) ? $payload['status'] : ($existing['prev_status'] ?? 'active');
        if (!in_array($payload['status'], SERVICES_VALID_STATUSES, true)) {
            $payload['status'] = 'active';
        }
        $payload['prev_status'] = null;
        $payload['archived_at'] = null;
    }
}

if (empty($payload)) {
    json_response(false, 'No fields to update.', null, 422);
}

$payload['updated_at'] = date('c');
$response = services_patch_by_identifier($id, $payload);
if (in_array($response['status'], [200, 204], true)) {
    $updated = is_array($response['body']) ? ($response['body'][0] ?? $response['body']) : $response['body'];
    json_response(true, 'Service updated successfully.', $updated);
}

services_handle_supabase_error($response, 'Failed to update service.');

