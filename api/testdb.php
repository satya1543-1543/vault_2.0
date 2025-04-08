<?php
require_once __DIR__ . '/helpers.php';

try {
    $pdo = getDbConnection();
    echo json_encode(['status' => 'success', 'message' => 'DB Connected Successfully!']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

