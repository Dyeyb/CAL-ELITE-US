<?php
require_once __DIR__ . '/common.php';

services_require_method(['GET', 'OPTIONS']);

$response = services_fetch_all();
if ($response['status'] === 200) {
    json_response(true, 'Services fetched successfully.', $response['body']);
}

services_handle_supabase_error($response, 'Failed to fetch services.');

