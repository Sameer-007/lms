<?php
// Prevent PHP warnings/notices from corrupting JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---- JWT Library ----
require_once __DIR__ . '/../utils/src/JWT.php';
require_once __DIR__ . '/../utils/src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// SECRET KEY
$JWT_SECRET = "YOUR_SECRET_KEY";

// JWT Create Function
function createJWT($payload) {
    global $JWT_SECRET;
    return JWT::encode($payload, $JWT_SECRET, "HS256");
}

require_once __DIR__ . "/../../db.php";

// Read JSON body
$data = json_decode(file_get_contents("php://input"), true);

// Validate request
if (!isset($data["phone"]) || !isset($data["password"])) {
    echo json_encode(["success" => false, "message" => "Missing phone or password"]);
    exit;
}

// Fetch student (phone login)
$stmt = $pdo->prepare("SELECT * FROM students WHERE phone=? LIMIT 1");
$stmt->execute([$data["phone"]]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s) {
    echo json_encode(["success"=>false, "message"=>"Phone not registered"]);
    exit;
}

// Verify hashed password
if (!password_verify($data["password"], $s["password"])) {
    echo json_encode(["success"=>false, "message"=>"Wrong password"]);
    exit;
}

// JWT payload (using ?? null to prevent undefined key warnings)
$payload = [
    "id"         => $s["id"],
    "role"       => "student",
    "admin_id"   => $s["admin_id"] ?? null,
    "teacher_id" => $s["teacher_id"] ?? null,
    "class"      => $s["class"] ?? null,
    "exp"        => time() + (60 * 60 * 24 * 7)  // 7 days
];

// Create JWT token
$token = createJWT($payload);

// Remove password hash before sending to client
unset($s["password"]);

// Response
echo json_encode([
    "success" => true,
    "token" => $token,
    "user" => $s
]);
?>
