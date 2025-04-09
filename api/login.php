<?php
require_once __DIR__ . '/helpers.php';

// --- Rate Limiting Configuration ---
define('MAX_LOGIN_ATTEMPTS', 5); // Max failed attempts allowed
define('LOGIN_ATTEMPT_WINDOW_SECONDS', 300); // Time window in seconds (5 minutes)
// --- End Configuration ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    outputJson(['error' => 'POST method required']);
}

// Get client IP Address (Basic - consider proxy headers if needed)
$clientIp = $_SERVER['REMOTE_ADDR'];

// Get input data (expecting JSON)
$input = json_decode(file_get_contents('php://input'), true);
$enteredPin = $input['pin'] ?? null;

// --- Database Connection (needed early for rate limiting check) ---
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    // Log critical error: Cannot connect to DB for login/rate limiting
    error_log("Database connection failed in login.php: " . $e->getMessage());
    http_response_code(500);
    outputJson(['error' => 'Server error: Could not connect to database.']);
}
// --- End Database Connection ---


// --- Rate Limiting Check ---
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts
                           WHERE ip_address = ?
                           AND attempt_timestamp >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->execute([$clientIp, LOGIN_ATTEMPT_WINDOW_SECONDS]);
    $result = $stmt->fetch();
    $failedAttempts = $result ? (int)$result['attempt_count'] : 0;

    if ($failedAttempts >= MAX_LOGIN_ATTEMPTS) {
        http_response_code(429); // Too Many Requests
        outputJson(['error' => 'Too many failed login attempts. Please try again later.']);
    }

} catch (PDOException $e) {
    // Log error: Failed rate limiting check
    error_log("Rate limiting check failed for IP $clientIp: " . $e->getMessage());
    // Allow login attempt but log the error, or block? Decide based on security policy.
    // For now, we'll let it proceed but the error is logged. A stricter policy might block here.
    // http_response_code(500);
    // outputJson(['error' => 'Server error during security check.']);
}
// --- End Rate Limiting Check ---


// --- PIN Validation and Login Logic ---
if (empty($enteredPin) || !preg_match('/^\d{4}$/', $enteredPin)) {
    http_response_code(400); // Bad Request
    outputJson(['error' => 'Invalid PIN format submitted']);
}

try {
    // Fetch the stored PIN hash
    $stmt = $pdo->prepare("SELECT config_value FROM vault_config WHERE config_key = 'master_pin_hash'");
    $stmt->execute();
    $result = $stmt->fetch();

    if (!$result || empty($result['config_value'])) {
        // PIN not configured - Server Error (500 is acceptable, 503 Service Unavailable is also an option)
        http_response_code(500);
        outputJson(['error' => 'Vault PIN not configured on server. Setup required.']);
    }

    $storedHash = $result['config_value'];

    // Verify the entered PIN against the stored hash
    if (password_verify($enteredPin, $storedHash)) {
        // --- Login Successful ---

        // 1. Clear failed attempts for this IP
        try {
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->execute([$clientIp]);
        } catch (PDOException $e) {
            // Log error: Failed to clear attempts, but proceed with login
            error_log("Failed to clear login attempts for IP $clientIp: " . $e->getMessage());
        }

        // 2. Start session securely
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_samesite' => 'Strict'
        ]);
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true; // Use a consistent key, e.g., 'authenticated' or 'loggedin'
        outputJson(['success' => true]);

    } else {
        // --- Login Failed (Incorrect PIN) ---

        // 1. Record the failed attempt
        try {
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
            $stmt->execute([$clientIp]);
        } catch (PDOException $e) {
            // Log error: Failed to record attempt
            error_log("Failed to record failed login attempt for IP $clientIp: " . $e->getMessage());
        }

        // 2. Return Unauthorized error
        http_response_code(401); // Unauthorized
        outputJson(['error' => 'Incorrect PIN']);
    }

} catch (PDOException $e) {
    // Log error in production
    error_log("Database error during login process for IP $clientIp: " . $e->getMessage());
    http_response_code(500);
    outputJson(['error' => 'Database error during login']);
} catch (Exception $e) {
    // Log error in production
    error_log("Unexpected error during login process for IP $clientIp: " . $e->getMessage());
    http_response_code(500);
    outputJson(['error' => 'An unexpected error occurred']);
}
?>
