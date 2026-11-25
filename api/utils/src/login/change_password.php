<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include '../db.php';
include '../auth.php';

if (!in_array($role, ['Admin', 'Teacher', 'Student', 'Parent'])) {
    http_response_code(403);
    echo json_encode(["error" => "Permission denied"]);
    exit;
}

try {
    if (!$id) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $currentPassword = trim($data['oldPassword'] ?? '');
    $newPassword = trim($data['newPassword'] ?? '');

    if (empty($currentPassword) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(["error" => "All fields are required"]);
        exit;
    }

    $pdo->beginTransaction();

    // Fetch current password hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE reference_id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    // Verify old password
    if (!password_verify($currentPassword, $row['password_hash'])) {
        echo json_encode(["success" => false, "message" => "Current password is incorrect"]);
        exit;
    }

    // Hash and update new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE reference_id = ?");
    $updateStmt->execute([$hashedPassword, $id]);

    $pdo->commit();

    echo json_encode(["success" => true, "message" => "Password updated successfully"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
