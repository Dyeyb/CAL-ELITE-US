<?php
require_once __DIR__ . '/../../../../Login/db-config.php';

if (!defined('SUPABASE_SERVICES_TABLE')) {
    define('SUPABASE_SERVICES_TABLE', 'services');
}

if (!defined('SERVICES_VALID_CATEGORIES')) {
    define('SERVICES_VALID_CATEGORIES', [
        'installation',
        'fabrication',
        'consultation',
        'maintenance',
        'custom',
    ]);
}

if (!defined('SERVICES_VALID_STATUSES')) {
    define('SERVICES_VALID_STATUSES', [
        'active',
        'inactive',
        'draft',
        'archived',
    ]);
}

if (!defined('SUPABASE_STORAGE_BUCKET')) {
    define('SUPABASE_STORAGE_BUCKET', getenv('SUPABASE_STORAGE_BUCKET') ?: 'services-images');
}

if (!function_exists('services_require_method')) {
    function services_require_method(array $allowed): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'OPTIONS') {
            json_response(true, 'OK', null, 200);
        }
        if (!in_array($method, $allowed, true)) {
            json_response(false, 'Method not allowed.', null, 405);
        }
    }
}

if (!function_exists('services_get_input')) {
    function services_get_input(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('services_id_param')) {
    function services_id_param(): string
    {
        return trim((string)($_GET['id'] ?? ''));
    }
}

if (!function_exists('services_parse_bool')) {
    function services_parse_bool($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }
        if (!is_string($value)) {
            return null;
        }
        $v = strtolower(trim($value));
        if (in_array($v, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'false', 'no'], true)) {
            return false;
        }
        return null;
    }
}

if (!function_exists('services_validate_payload')) {
    function services_validate_payload(array $input, bool $partial = false): array
    {
        $payload = [];
        $errors = [];

        $required = ['name', 'description', 'category', 'status', 'price', 'image_url'];
        foreach ($required as $field) {
            if (!$partial && !array_key_exists($field, $input)) {
                $errors[] = ucfirst($field) . ' is required.';
            }
        }

        if (array_key_exists('name', $input)) {
            $value = trim((string)$input['name']);
            if ($value === '') {
                $errors[] = 'Name is required.';
            } else {
                $payload['name'] = $value;
            }
        }

        if (array_key_exists('description', $input)) {
            $value = trim((string)$input['description']);
            if ($value === '') {
                $errors[] = 'Description is required.';
            } else {
                $payload['description'] = $value;
            }
        }

        if (array_key_exists('category', $input)) {
            $value = strtolower(trim((string)$input['category']));
            if (!in_array($value, SERVICES_VALID_CATEGORIES, true)) {
                $errors[] = 'Invalid category. Allowed: ' . implode(', ', SERVICES_VALID_CATEGORIES);
            } else {
                $payload['category'] = $value;
            }
        }

        if (array_key_exists('status', $input)) {
            $value = strtolower(trim((string)$input['status']));
            if (!in_array($value, SERVICES_VALID_STATUSES, true)) {
                $errors[] = 'Invalid status. Allowed: ' . implode(', ', SERVICES_VALID_STATUSES);
            } else {
                $payload['status'] = $value;
            }
        }

        if (array_key_exists('price', $input)) {
            $raw = $input['price'];
            if ($raw === null || $raw === '') {
                $errors[] = 'Price is required.';
            } elseif (is_numeric($raw)) {
                $price = (float)$raw;
                if ($price < 0) {
                    $errors[] = 'Price cannot be negative.';
                } else {
                    $payload['price'] = $price;
                    // Keep backwards compatibility with old rendering fields.
                    $payload['price_min'] = $price;
                }
            } else {
                $errors[] = 'Price must be numeric.';
            }
        }

        foreach (['price_min', 'price_max'] as $field) {
            if (array_key_exists($field, $input)) {
                $raw = $input[$field];
                if ($raw === null || $raw === '') {
                    $payload[$field] = null;
                } elseif (is_numeric($raw)) {
                    $payload[$field] = (float)$raw;
                } else {
                    $errors[] = str_replace('_', ' ', ucfirst($field)) . ' must be numeric.';
                }
            }
        }

        if (
            isset($payload['price_min'], $payload['price_max']) &&
            $payload['price_min'] !== null &&
            $payload['price_max'] !== null &&
            $payload['price_min'] > $payload['price_max']
        ) {
            $errors[] = 'price_min cannot be greater than price_max.';
        }

        if (array_key_exists('duration', $input)) {
            $payload['duration'] = trim((string)$input['duration']);
        }

        if (array_key_exists('image_url', $input)) {
            $value = trim((string)$input['image_url']);
            if ($value === '') {
                $errors[] = 'Image URL is required.';
            } elseif (!filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[] = 'Image URL must be a valid URL.';
            } else {
                $payload['image_url'] = $value;
            }
        }

        if (array_key_exists('image_key', $input)) {
            $payload['image_key'] = trim((string)$input['image_key']);
        }

        if (array_key_exists('is_archived', $input)) {
            $parsed = services_parse_bool($input['is_archived']);
            if ($parsed === null) {
                $errors[] = 'is_archived must be true or false.';
            } else {
                $payload['is_archived'] = $parsed;
            }
        }

        return [$payload, $errors];
    }
}

if (!function_exists('services_fetch_all')) {
    function services_fetch_all(): array
    {
        $query = [
            'select' => 'service_id,name,description,category,status,price,image_url,image_key,price_min,price_max,duration,is_archived,prev_status,archived_at,created_at,updated_at',
            'order' => 'created_at.desc',
        ];
        return supabase_request('GET', SUPABASE_SERVICES_TABLE, null, $query);
    }
}

if (!function_exists('services_find_by_identifier')) {
    function services_find_by_identifier(string $id): ?array
    {
        $queryByServiceId = [
            'service_id' => 'eq.' . $id,
            'select' => '*',
            'limit' => '1',
        ];
        $byServiceId = supabase_request('GET', SUPABASE_SERVICES_TABLE, null, $queryByServiceId);
        if ($byServiceId['status'] === 200 && !empty($byServiceId['body'])) {
            return $byServiceId['body'][0];
        }

        $queryById = [
            'id' => 'eq.' . $id,
            'select' => '*',
            'limit' => '1',
        ];
        $byId = supabase_request('GET', SUPABASE_SERVICES_TABLE, null, $queryById);
        if ($byId['status'] === 200 && !empty($byId['body'])) {
            return $byId['body'][0];
        }

        return null;
    }
}

if (!function_exists('services_patch_by_identifier')) {
    function services_patch_by_identifier(string $id, array $payload): array
    {
        $byServiceId = supabase_request(
            'PATCH',
            SUPABASE_SERVICES_TABLE,
            $payload,
            ['service_id' => 'eq.' . $id]
        );
        if (in_array($byServiceId['status'], [200, 204], true) && !empty($byServiceId['body'])) {
            return $byServiceId;
        }

        $byId = supabase_request(
            'PATCH',
            SUPABASE_SERVICES_TABLE,
            $payload,
            ['id' => 'eq.' . $id]
        );
        return $byId;
    }
}

if (!function_exists('services_delete_by_identifier')) {
    function services_delete_by_identifier(string $id): array
    {
        $byServiceId = supabase_request(
            'DELETE',
            SUPABASE_SERVICES_TABLE,
            null,
            ['service_id' => 'eq.' . $id]
        );
        if (in_array($byServiceId['status'], [200, 204], true) && !empty($byServiceId['body'])) {
            return $byServiceId;
        }

        $byId = supabase_request(
            'DELETE',
            SUPABASE_SERVICES_TABLE,
            null,
            ['id' => 'eq.' . $id]
        );
        return $byId;
    }
}

if (!function_exists('services_handle_supabase_error')) {
    function services_handle_supabase_error(array $response, string $fallback): void
    {
        if (($response['status'] ?? 0) === 0) {
            json_response(false, $response['body']['error'] ?? 'Connection failed.', null, 500);
        }
        $body = $response['body'] ?? [];
        $msg = is_array($body) ? ($body['message'] ?? null) : null;
        if ($msg === null || $msg === '') {
            $msg = $fallback . ' (HTTP ' . ($response['status'] ?? 500) . ')';
        }
        json_response(false, $msg, $body, 500);
    }
}

if (!function_exists('services_storage_is_configured')) {
    function services_storage_is_configured(): bool
    {
        return SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '' && SUPABASE_STORAGE_BUCKET !== '';
    }
}

if (!function_exists('services_build_storage_public_url')) {
    function services_build_storage_public_url(string $key): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
        return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . rawurlencode(SUPABASE_STORAGE_BUCKET) . '/' . $encoded;
    }
}

if (!function_exists('services_upload_image_to_storage')) {
    function services_upload_image_to_storage(array $file): array
    {
        if (!services_storage_is_configured()) {
            return ['ok' => false, 'error' => 'Supabase Storage is not configured.'];
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Image upload failed.'];
        }

        $tmpPath = $file['tmp_name'] ?? '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['ok' => false, 'error' => 'Invalid uploaded file.'];
        }

        $maxBytes = 5 * 1024 * 1024;
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'error' => 'Image must be between 1 byte and 5MB.'];
        }

        $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP, and GIF are allowed.'];
        }

        $ext = $allowed[$mime];
        $key = 'services/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));
        $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . rawurlencode(SUPABASE_STORAGE_BUCKET) . '/' . $encodedKey;
        $body = file_get_contents($tmpPath);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: ' . $mime,
                'apikey: ' . SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . SUPABASE_ANON_KEY,
                'x-upsert: false',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['ok' => false, 'error' => 'Storage upload error: ' . $curlError];
        }
        if (!in_array($status, [200, 201], true)) {
            $decoded = json_decode((string)$response, true);
            $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? null) : null;
            return ['ok' => false, 'error' => ($msg ?: 'Storage upload failed with HTTP ' . $status . '.')];
        }

        return [
            'ok' => true,
            'key' => $key,
            'url' => services_build_storage_public_url($key),
        ];
    }
}
