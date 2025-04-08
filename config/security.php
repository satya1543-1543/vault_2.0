<?php
// ** IMPORTANT: Store this key securely! **
// Generate a strong, random key ONCE and store it safely.
// DO NOT commit this key to version control if it's hardcoded here.
// Place this file OUTSIDE the web root if possible.

// Generate using: echo base64_encode(openssl_random_pseudo_bytes(32));
define('ENCRYPTION_KEY', 'A4yBgNK1/fPz0qb5ksEEZu9jkZ0HV5HIb6g3pOFYjjo='); // Replace with your actual key
define('ENCRYPTION_CIPHER', 'aes-256-cbc'); // AES 256-bit encryption in CBC mode

if (strlen(base64_decode(ENCRYPTION_KEY)) !== 32) {
    // Ensure the key is the correct length for AES-256
    die("Encryption key must be 32 bytes (after base64 decoding).");
}
?>