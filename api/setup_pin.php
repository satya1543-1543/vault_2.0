<?php
require_once __DIR__ . '/api/helpers.php'; // To get DB connection

// --- !!! RUN THIS SCRIPT ONLY ONCE !!! ---
// --- !!! DELETE OR SECURE IT AFTERWARDS !!! ---

$masterPin = "1541"; // CHANGE THIS TO YOUR DESIRED 4-DIGIT PIN

if (empty($masterPin) || !preg_match('/^\d{4}$/', $masterPin)) {
    die("Invalid PIN format. Please provide exactly 4 digits in the script.");
}

// Hash the PIN securely
$hashedPin = password_hash($masterPin, PASSWORD_ARGON2ID); // Or PASSWORD_BCRYPT

if ($hashedPin === false) {
    die("Failed to hash the PIN.");
}

try {
    $pdo = getDbConnection();

    // Check if PIN already exists
    $stmt = $pdo->prepare("SELECT config_value FROM vault_config WHERE config_key = 'master_pin_hash'");
    $stmt->execute();
    if ($stmt->fetch()) {
         die("Master PIN hash already exists in the database. Delete the existing row if you want to reset it.");
    }

    // Insert the hashed PIN
    $stmt = $pdo->prepare("INSERT INTO vault_config (config_key, config_value) VALUES ('master_pin_hash', ?)");
    if ($stmt->execute([$hashedPin])) {
        echo "Master PIN hash successfully stored in the database.<br>";
        echo "<b>IMPORTANT: DELETE OR SECURE THIS setup_pin.php FILE NOW!</b>";
    } else {
        echo "Failed to store the master PIN hash.";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>