<?php
/**
 * Supabase Database Configuration
 * 
 * This file handles all connections to your Supabase database
 * and provides helper functions for API requests.
 * 
 * Uses function_exists() guards to prevent redeclaration errors
 * when file is included multiple times.
 */

// ══════════════════════════════════════════════════════════════════
// SUPABASE CREDENTIALS
// ══════════════════════════════════════════════════════════════════

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', 'https://pdqhbxtxvxrwtkvymjlm.supabase.co');
}

if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBkcWhieHR4dnhyd3RrdnltamxtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE1NTEyMzIsImV4cCI6MjA4NzEyNzIzMn0.jKq6Zw1XWDYXkxdrkW6HscOpsOuUm0gcyBCwFsAwN9U');
}

// ══════════════════════════════════════════════════════════════════
// TABLE NAMES
// ══════════════════════════════════════════════════════════════════

if (!defined('SUPABASE_USERS_TABLE')) {
    define('SUPABASE_USERS_TABLE', 'Users');
}

if (!defined('SUPABASE_OTP_TABLE')) {
    define('SUPABASE_OTP_TABLE', 'OTP_Verifications');
}


// ══════════════════════════════════════════════════════════════════
// SUPABASE API REQUEST FUNCTION
// ══════════════════════════════════════════════════════════════════

if (!function_exists('supabase_request')) {
    /**
     * Make a request to Supabase REST API
     * 
     * @param string $method HTTP method (GET, POST, PATCH, DELETE, etc.)
     * @param string $endpoint Table name or endpoint path
     * @param array|null $body Request body for POST/PATCH
     * @param array|null $query Query parameters (filter, select, etc.)
     * 
     * @return array Response array with 'status' and 'body' keys
     *               status: HTTP status code (200, 201, 404, 500, etc.)
     *               body: Decoded JSON response
     */
    function supabase_request(
        string $method,
        string $endpoint,
        ?array $body = null,
        ?array $query = null
        ): array
    {
        $url = SUPABASE_URL . '/rest/v1/' . $endpoint;

        // Add query parameters if provided
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
            'Prefer: return=representation',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        // Add body for POST/PATCH requests
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($curlError) {
            return [
                'status' => 0,
                'body' => ['error' => 'cURL Error: ' . $curlError]
            ];
        }

        // Decode response
        $decoded = json_decode($response, true);

        return [
            'status' => $httpStatus,
            'body' => $decoded ?? $response
        ];
    }
}


// ══════════════════════════════════════════════════════════════════
// JSON RESPONSE HELPER
// ══════════════════════════════════════════════════════════════════

if (!function_exists('json_response')) {
    /**
     * Send a JSON response to the client
     * 
     * @param bool $success Operation success status
     * @param string $message Response message
     * @param mixed $data Additional data to include
     * @param int $httpCode HTTP status code
     * 
     * @return void (exits after sending response)
     */
    function json_response(
        bool $success,
        string $message,
        $data = null,
        int $httpCode = 200
        ): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Handle OPTIONS requests for CORS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        exit;
    }
}


// ══════════════════════════════════════════════════════════════════
// DATABASE HELPER FUNCTIONS
// ══════════════════════════════════════════════════════════════════

if (!function_exists('get_user_by_email')) {
    /**
     * Get a single user by email
     * 
     * @param string $email User email address
     * 
     * @return array|null User data or null if not found
     */
    function get_user_by_email(string $email): ?array
    {
        // Normalize email: lowercase and trim
        $email = strtolower(trim($email));

        // Use eq without urlencode to avoid Supabase API issues
        // Supabase handles special characters better without URL encoding
        $query = [
            'email' => 'eq.' . $email,
            'select' => '*'
        ];

        $response = supabase_request('GET', SUPABASE_USERS_TABLE, null, $query);

        if ($response['status'] === 200 && !empty($response['body'])) {
            return $response['body'][0];
        }

        return null;
    }
}


if (!function_exists('get_user_by_id')) {
    /**
     * Get a single user by ID
     * 
     * @param int $user_id User ID
     * 
     * @return array|null User data or null if not found
     */
    function get_user_by_id(int $user_id): ?array
    {
        $query = [
            'user_id' => 'eq.' . $user_id,
            'select' => '*'
        ];

        $response = supabase_request('GET', SUPABASE_USERS_TABLE, null, $query);

        if ($response['status'] === 200 && !empty($response['body'])) {
            return $response['body'][0];
        }

        return null;
    }
}


if (!function_exists('email_exists')) {
    /**
     * Check if email exists in database
     * 
     * @param string $email Email address to check
     * 
     * @return bool True if email exists, false otherwise
     */
    function email_exists(string $email): bool
    {
        return get_user_by_email($email) !== null;
    }
}


if (!function_exists('get_latest_otp')) {
    /**
     * Get latest OTP for a user
     * 
     * @param string $email User email
     * @param string $type OTP type (default: 'Account Activation')
     * 
     * @return array|null OTP record or null if not found
     */
    function get_latest_otp(string $email, string $type = 'Account Activation'): ?array
    {
        $query = [
            'email' => 'eq.' . urlencode($email),
            'type' => 'eq.' . urlencode($type),
            'used' => 'eq.false',
            'select' => '*',
            'order' => 'created_at.desc',
            'limit' => '1'
        ];

        $response = supabase_request('GET', SUPABASE_OTP_TABLE, null, $query);

        if ($response['status'] === 200 && !empty($response['body'])) {
            return $response['body'][0];
        }

        return null;
    }
}


if (!function_exists('create_otp')) {
    /**
     * Create a new OTP record
     * 
     * @param int $user_id User ID
     * @param string $email User email
     * @param string $otp_hash Hashed OTP
     * @param string $expires_at Expiration timestamp
     * @param string $type OTP type
     * 
     * @return array|null Created OTP record or null on failure
     */
    function create_otp(
        int $user_id,
        string $email,
        string $otp_hash,
        string $expires_at,
        string $type = 'Account Activation'
        ): ?array
    {
        $body = [
            'user_id' => $user_id,
            'email' => $email,
            'otp_hash' => $otp_hash,
            'expires_at' => $expires_at,
            'used' => false,
            'type' => $type,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $response = supabase_request('POST', SUPABASE_OTP_TABLE, $body);

        if ($response['status'] === 201 && !empty($response['body'])) {
            return $response['body'][0];
        }

        return null;
    }
}


if (!function_exists('mark_otp_as_used')) {
    /**
     * Mark OTP as used
     * 
     * @param int $otp_id OTP record ID
     * 
     * @return bool True if successful, false otherwise
     */
    function mark_otp_as_used(int $otp_id): bool
    {
        $body = ['used' => true];

        $query = ['id' => 'eq.' . $otp_id];

        $response = supabase_request('PATCH', SUPABASE_OTP_TABLE, $body, $query);

        return $response['status'] === 200;
    }
}


if (!function_exists('update_user_status')) {
    /**
     * Update user account status
     * 
     * @param int $user_id User ID
     * @param string $status New status ('active', 'suspended', 'archived')
     * @param bool $is_archived Archive status
     * 
     * @return bool True if successful, false otherwise
     */
    function update_user_status(
        int $user_id,
        string $status = 'active',
        bool $is_archived = false
        ): bool
    {
        $body = [
            'status' => $status,
            'is_archived' => $is_archived,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $query = ['user_id' => 'eq.' . $user_id];

        $response = supabase_request('PATCH', SUPABASE_USERS_TABLE, $body, $query);

        return $response['status'] === 200;
    }
}


if (!function_exists('verify_password')) {
    /**
     * Verify password hash
     * 
     * @param string $plain_password Plain text password
     * @param string $password_hash Hashed password from database
     * 
     * @return bool True if password matches, false otherwise
     */
    function verify_password(string $plain_password, string $password_hash): bool
    {
        return password_verify($plain_password, $password_hash);
    }
}


if (!function_exists('hash_password')) {
    /**
     * Hash a password using bcrypt
     * 
     * @param string $password Plain text password
     * 
     * @return string Hashed password
     */
    function hash_password(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}


// ══════════════════════════════════════════════════════════════════
// ERROR LOGGING
// ══════════════════════════════════════════════════════════════════

if (!function_exists('log_error')) {
    /**
     * Log errors to a file
     * 
     * @param string $message Error message
     * @param string $level Log level (ERROR, WARNING, INFO)
     * 
     * @return void
     */
    function log_error(string $message, string $level = 'ERROR'): void
    {
        $log_file = __DIR__ . '/logs/app.log';
        $log_dir = dirname($log_file);

        // Create logs directory if it doesn't exist
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message\n";

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}


// ══════════════════════════════════════════════════════════════════
// SESSION MANAGEMENT
// ══════════════════════════════════════════════════════════════════

if (!function_exists('start_secure_session')) {
    /**
     * Start secure session
     * 
     * @return void
     */
    function start_secure_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => true,
                'cookie_samesite' => 'Strict',
            ]);
        }
    }
}


if (!function_exists('get_current_user')) {
    /**
     * Get current user from session
     * 
     * @return array|null Current user data or null if not logged in
     */
    function get_current_user(): ?array
    {
        start_secure_session();

        return $_SESSION['user'] ?? null;
    }
}


if (!function_exists('set_user_session')) {
    /**
     * Set user in session
     * 
     * @param array $user User data
     * 
     * @return void
     */
    function set_user_session(array $user): void
    {
        start_secure_session();

        $_SESSION['user'] = $user;
    }
}


if (!function_exists('clear_user_session')) {
    /**
     * Clear user session
     * 
     * @return void
     */
    function clear_user_session(): void
    {
        start_secure_session();

        unset($_SESSION['user']);
        session_destroy();
    }
}


// ══════════════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ══════════════════════════════════════════════════════════════════

if (!function_exists('is_valid_email')) {
    /**
     * Validate email format
     * 
     * @param string $email Email address
     * 
     * @return bool True if valid, false otherwise
     */
    function is_valid_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}


if (!function_exists('sanitize_input')) {
    /**
     * Sanitize input string
     * 
     * @param string $input Input string
     * 
     * @return string Sanitized string
     */
    function sanitize_input(string $input): string
    {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}


if (!function_exists('get_client_ip')) {
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    function get_client_ip(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
    }
}


if (!function_exists('generate_random_string')) {
    /**
     * Generate random string
     * 
     * @param int $length Length of string
     * 
     * @return string Random string
     */
    function generate_random_string(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}

?>