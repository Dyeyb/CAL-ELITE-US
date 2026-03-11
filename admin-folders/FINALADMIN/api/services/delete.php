<?php
require_once __DIR__ . '/common.php';

services_require_method(['DELETE', 'OPTIONS']);

$id = services_id_param();
if ($id === '') {
    json_response(false, 'Service id is required.', null, 400);
}

$existing = services_find_by_identifier($id);
if ($existing === null) {
    json_response(false, 'Service not found.', null, 404);
}

$response = services_delete_by_identifier($id);
if (in_array($response['status'], [200, 204], true)) {
    json_response(true, 'Service deleted successfully.');
}

services_handle_supabase_error($response, 'Failed to delete service.');

