<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Include JWT core files
require_once __DIR__ . "/src/JWT.php";
require_once __DIR__ . "/src/Key.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "YOUR_SECRET_KEY";

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: Token missing"]);
    exit;
}

$jwt = $matches[1];

// Verify token
try {
    $auth = JWT::decode($jwt, new Key($secret_key, 'HS256'));
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: Invalid token"]);
    exit;
}
?>
