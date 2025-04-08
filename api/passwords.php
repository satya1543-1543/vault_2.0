<?php
require_once __DIR__ . '/helpers.php';

// Ensure user is authenticated for all actions on passwords
ensureAuthenticated();

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Fetch all password entries
            $stmt = $pdo->query("SELECT id, website_name, username, encrypted_password, iv FROM password_entries ORDER BY website_name ASC");
            $entries = $stmt->fetchAll();

            $decryptedEntries = [];
            foreach ($entries as $entry) {
                // Decrypt password before sending to frontend
                $decryptedPassword = decryptPassword($entry['encrypted_password'], $entry['iv']);
                $decryptedEntries[] = [
                    'id' => $entry['id'],
                    'website' => $entry['website_name'], // Match frontend expected key
                    'username' => $entry['username'],
                    'password' => $decryptedPassword // Send decrypted password
                ];
            }
            outputJson($decryptedEntries);
            break;

        case 'POST':
            // Add a new password entry
            $input = json_decode(file_get_contents('php://input'), true);

            $website = filter_var($input['website'] ?? '', FILTER_SANITIZE_STRING);
            $username = filter_var($input['username'] ?? '', FILTER_SANITIZE_STRING);
            $password = $input['password'] ?? ''; // Don't sanitize password itself

            if (empty($website) || empty($username) || empty($password)) {
                http_response_code(400);
                outputJson(['error' => 'Missing required fields']);
            }

            // Encrypt the password
            $encryptionResult = encryptPassword($password);
            $encryptedPassword = $encryptionResult['encrypted'];
            $iv = $encryptionResult['iv'];

            $stmt = $pdo->prepare("INSERT INTO password_entries (website_name, username, encrypted_password, iv) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$website, $username, $encryptedPassword, $iv])) {
                // Return the newly created entry (optional, but can be useful)
                 $newId = $pdo->lastInsertId();
                 outputJson([
                     'success' => true,
                     'id' => $newId,
                     'website' => $website,
                     'username' => $username,
                     'password' => $password // Return plain text for immediate UI update if needed
                 ]);
                 // Or just: outputJson(['success' => true, 'id' => $newId]);
            } else {
                http_response_code(500);
                outputJson(['error' => 'Failed to save entry']);
            }
            break;

        case 'DELETE':
            // Delete a password entry
            // Get ID from query string, e.g., /api/passwords.php?id=5
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if (!$id) {
                http_response_code(400);
                outputJson(['error' => 'Invalid or missing ID for deletion']);
            }

            $stmt = $pdo->prepare("DELETE FROM password_entries WHERE id = ?");
            if ($stmt->execute([$id])) {
                if ($stmt->rowCount() > 0) {
                    outputJson(['success' => true]);
                } else {
                    http_response_code(404); // Not Found
                    outputJson(['error' => 'Entry not found']);
                }
            } else {
                http_response_code(500);
                outputJson(['error' => 'Failed to delete entry']);
            }
            break;

        default:
            http_response_code(405); // Method Not Allowed
            outputJson(['error' => 'Method not supported']);
            break;
    }
} catch (PDOException $e) {
    // Log error in production
    http_response_code(500);
    outputJson(['error' => 'Database error: ' . $e->getMessage()]); // Show specific error for debugging only
} catch (Exception $e) {
    // Log error in production
    http_response_code(500);
    outputJson(['error' => 'Error: ' . $e->getMessage()]); // Show specific error for debugging only
}
?>