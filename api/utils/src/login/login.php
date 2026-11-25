<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

include '../db.php';
require_once '../src/JWT.php';
require_once '../src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = "YOUR_SECRET_KEY";

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));

$username = $data->username ?? '';
$password = $data->password ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Username and password required"]);
    exit;
}

// Check user from DB
$query = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'Active'");
$query->execute([$username]);
$user = $query->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

// JWT Payload
$payload = [
    "iss" => "your-school-system",
    "iat" => time(),
    "exp" => time() + (60 * 60 * 24), // 24 hours
    "id" => $user['reference_id'],
    "role" => $user['role'],
    "name" => $user['name'],
    "force_password_change" => $user['must_change_password'],
];

// Secret key
$secret_key = "YOUR_SECRET_KEY";
$jwt = JWT::encode($payload, $secret_key, 'HS256');

echo json_encode([
    "message" => "Login successful",
    "token" => $jwt,
    "role" => $user['role'],
    "must_change_password" => $user['must_change_password'],
]);
