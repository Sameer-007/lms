<?php
/**
 * Teacher Login API (LMS v2)
 * Authenticates faculty members and issues a JWT token with 'teacher' role.
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../utils/src/JWT.php';
require_once __DIR__ . '/../utils/src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$JWT_SECRET = "YOUR_SECRET_KEY";

function createJWT($payload) {
    global $JWT_SECRET;
    return JWT::encode($payload, $JWT_SECRET, "HS256");
}

require_once __DIR__ . "/../../db.php";

$data = json_decode(file_get_contents("php://input"), true) ?? [];

$phone = trim($data["phone"] ?? $_POST["phone"] ?? "");
$password = trim($data["password"] ?? $_POST["password"] ?? "");

if (empty($phone) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Phone number and password are required."]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM teacher WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        echo json_encode(["success" => false, "message" => "Teacher with this phone number is not registered."]);
        exit;
    }

    if (!password_verify($password, $teacher["password"])) {
        echo json_encode(["success" => false, "message" => "Incorrect password. Please try again."]);
        exit;
    }

    $payload = [
        "id"       => (int)$teacher["id"],
        "name"     => $teacher["name"],
        "phone"    => $teacher["phone"],
        "role"     => "teacher",
        "admin_id" => $teacher["admin_id"] ?? null,
        "exp"      => time() + (60 * 60 * 24 * 7) // 7 days
    ];

    $token = createJWT($payload);

    unset($teacher["password"]);

    echo json_encode([
        "success" => true,
        "token"   => $token,
        "user"    => [
            "id"       => (int)$teacher["id"],
            "name"     => $teacher["name"],
            "phone"    => $teacher["phone"],
            "role"     => "teacher",
            "admin_id" => $teacher["admin_id"] ?? null
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
