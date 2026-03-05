<?php
require_once '../config/supabase.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $response = supabase_request('GET', 'SystemSettings?select=*');

    if ($response['status'] >= 200 && $response['status'] < 300) {
        $settings = [];
        if (is_array($response['body'])) {
            foreach ($response['body'] as $row) {
                // Ensure the keys exist before parsing
                if (isset($row['setting_key'])) {
                    $settings[$row['setting_key']] = isset($row['setting_value']) ? $row['setting_value'] : '';
                }
            }
        }
        json_response(true, 'Settings retrieved successfully', $settings);
    } else {
        // Return an empty array indicating no settings yet, or echo the error
        json_response(false, 'Failed to retrieve settings', $response['body'], $response['status']);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input)) {
        json_response(false, 'Invalid payload', null, 400);
    }

    $payload = [];
    $timestamp = date('c');
    foreach ($input as $key => $value) {
        $val = '';
        if (is_bool($value)) {
            $val = $value ? 'true' : 'false';
        } else {
            $val = (string) $value;
        }
        $payload[] = [
            'setting_key' => $key,
            'setting_value' => $val,
            'updated_at' => $timestamp
        ];
    }

    // Custom cURL to set the 'Prefer: resolution=merge-duplicates' header for bulk UPSERT
    $url = SUPABASE_URL . '/rest/v1/SystemSettings';
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Prefer: resolution=merge-duplicates, return=minimal'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        json_response(false, 'cURL Error: ' . $curlError, null, 500);
    } elseif ($httpStatus >= 200 && $httpStatus < 300) {
        json_response(true, 'Settings updated successfully');
    } else {
        json_response(false, 'Failed to update settings', json_decode($response, true), $httpStatus);
    }
} else {
    json_response(false, 'Method Not Allowed', null, 405);
}
