<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// --- Database Connection ---
function getDbConnection() {
    static $pdo = null; // Static variable to reuse connection
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            // In production, log the error instead of echoing
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed.']);
            exit; // Stop script execution on connection failure
        }
    }
    return $pdo;
}

// --- Encryption ---
function encryptPassword($plaintext) {
    $key = base64_decode(ENCRYPTION_KEY);
    $ivlen = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext_raw = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext_raw === false) {
        throw new Exception("Encryption failed: " . openssl_error_string());
    }
    // Return IV and ciphertext together, base64 encoded for storage
    return [
        'iv' => base64_encode($iv),
        'encrypted' => base64_encode($ciphertext_raw)
    ];
}

// --- Decryption ---
function decryptPassword($base64_ciphertext, $base64_iv) {
    $key = base64_decode(ENCRYPTION_KEY);
    $iv = base64_decode($base64_iv);
    $ciphertext_raw = base64_decode($base64_ciphertext);
    $original_plaintext = openssl_decrypt($ciphertext_raw, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
     if ($original_plaintext === false) {
        // Log error in production
        // throw new Exception("Decryption failed: " . openssl_error_string());
        // Return a placeholder or handle error gracefully for the UI
        return "DECRYPTION_ERROR";
    }
    return $original_plaintext;
}

// --- Authentication Check ---
function ensureAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true, // Prevent JS access to session cookie
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Send only over HTTPS
            'cookie_samesite' => 'Strict' // Mitigate CSRF
        ]);
    }
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
}

// --- Output JSON ---
function outputJson($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>