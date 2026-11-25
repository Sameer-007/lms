<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// ---- JWT Library ----
require_once '../utils/src/JWT.php';
require_once '../utils/src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// SECRET KEY (must match login.php)
$JWT_SECRET = "YOUR_SECRET_KEY";

// Include DB
require_once __DIR__ . "/../../db.php";

// ----------------------------------------------------------
// 1️⃣ Get Authorization Header
// ----------------------------------------------------------
$headers = getallheaders();

if (!isset($headers["Authorization"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authorization header missing"]);
    exit;
}

$token = str_replace("Bearer ", "", $headers["Authorization"]);

// ----------------------------------------------------------
// 2️⃣ Decode JWT Token
// ----------------------------------------------------------
try {
    $decoded = JWT::decode($token, new Key($JWT_SECRET, "HS256"));

    // Convert to array
    $jwtData = (array) $decoded;

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Invalid or expired token",
        "error"   => $e->getMessage()
    ]);
    exit;
}

// ----------------------------------------------------------
// 3️⃣ Fetch user details from DB based on role
// ----------------------------------------------------------

$userId = $jwtData["id"] ?? 0;
$role   = $jwtData["role"] ?? "student";

$userResponse = null;

if ($role === 'teacher') {
    $stmt = $pdo->prepare("SELECT * FROM teacher WHERE id=? LIMIT 1");
    $stmt->execute([$userId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$t) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Teacher not found"]);
        exit;
    }

    $userResponse = [
        "id"         => (int)$t["id"],
        "name"       => $t["name"],
        "phone"      => $t["phone"],
        "role"       => "teacher",
        "admin_id"   => $t["admin_id"],
        "created_at" => $t["created_at"] ?? null
    ];

} elseif ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id=? LIMIT 1");
    $stmt->execute([$userId]);
    $adm = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$adm) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Admin not found"]);
        exit;
    }

    $userResponse = [
        "id"         => (int)$adm["id"],
        "name"       => $adm["name"] ?? "Admin",
        "phone"      => $adm["phone"],
        "role"       => "admin",
        "created_at" => $adm["created_at"] ?? null
    ];

} else {
    // Default: student
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id=? LIMIT 1");
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Student not found"]);
        exit;
    }

    $userResponse = [
        "id"         => (int)$student["id"],
        "name"       => $student["name"],
        "phone"      => $student["phone"],
        "role"       => "student",
        "admin_id"   => $student["admin_id"],
        "teacher_id" => $student["teacher_id"],
        "class"      => $student["class"] ?? null,
        "content"    => $student["content"] ?? null,
        "expiryDate" => $student["expiryDate"] ?? null,
        "created_at" => $student["created_at"] ?? null
    ];
}

// ----------------------------------------------------------
// 4️⃣ Return success response
// ----------------------------------------------------------

echo json_encode([
    "success" => true,
    "user" => $userResponse
]);
?>
