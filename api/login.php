<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    outputJson(['error' => 'POST method required']);
}

// Get input data (expecting JSON)
$input = json_decode(file_get_contents('php://input'), true);
$enteredPin = $input['pin'] ?? null;

if (empty($enteredPin) || !preg_match('/^\d{4}$/', $enteredPin)) {
    http_response_code(400); // Bad Request
    outputJson(['error' => 'Invalid PIN format submitted']);
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT config_value FROM vault_config WHERE config_key = 'master_pin_hash'");
    $stmt->execute();
    $result = $stmt->fetch();

    if (!$result || empty($result['config_value'])) {
        http_response_code(500); // Server error - PIN not set up
        outputJson(['error' => 'Vault PIN not configured on server.']);
    }

    $storedHash = $result['config_value'];

    // Verify the entered PIN against the stored hash
    if (password_verify($enteredPin, $storedHash)) {
        // Start session securely
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_samesite' => 'Strict'
        ]);
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        outputJson(['success' => true]);
    } else {
        http_response_code(401); // Unauthorized
        outputJson(['error' => 'Incorrect PIN']);
    }

} catch (PDOException $e) {
    // Log error in production
    http_response_code(500);
    outputJson(['error' => 'Database error during login']);
} catch (Exception $e) {
    // Log error in production
    http_response_code(500);
    outputJson(['error' => 'An unexpected error occurred']);
}
?>