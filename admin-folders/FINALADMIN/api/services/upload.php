<?php
require_once __DIR__ . '/common.php';

services_require_method(['POST', 'OPTIONS']);

if (!services_storage_is_configured()) {
    json_response(false, 'Supabase Storage is not configured on the server.', null, 500);
}

if (!isset($_FILES['image'])) {
    json_response(false, 'Image file is required.', null, 422);
}

$uploaded = services_upload_image_to_storage($_FILES['image']);
if (!$uploaded['ok']) {
    json_response(false, $uploaded['error'] ?? 'Failed to upload image.', null, 500);
}

json_response(true, 'Image uploaded successfully.', [
    'image_url' => $uploaded['url'],
    'image_key' => $uploaded['key'],
], 201);
