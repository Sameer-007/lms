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

// Only Admin can access
if ($role !== 'Admin') {
    http_response_code(403);
    echo json_encode(["error" => "Access denied"]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? '';

if (empty($id)) {
    echo json_encode(["error" => "ID is required"]);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // New password (fixed for now)
    $newPassword = "Teacher@123";
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update password and set must_change_password to 1
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = ?, must_change_password = 1 
        WHERE reference_id = ?
    ");
    $stmt->execute([$hash, $id]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode([
            "success" => true,
            "new_password" => $newPassword
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(["error" => "User not found or password unchanged"]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
