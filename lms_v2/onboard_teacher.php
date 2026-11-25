<?php
/**
 * Onboard Teacher API
 * Accepts POST (form-data or application/json)
 */
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib.php';

$pdo = db();

// Read input (supports both standard form POST and raw JSON)
$raw = json_decode(file_get_contents('php://input'), true) ?? [];
$name     = trim($_POST['name'] ?? $raw['name'] ?? '');
$phone    = trim($_POST['phone'] ?? $raw['phone'] ?? '');
$password = trim($_POST['password'] ?? $raw['password'] ?? '');
$subject  = trim($_POST['subject'] ?? $raw['subject'] ?? '');

if (empty($name) || empty($phone)) {
    sendJson([
        'Success' => false,
        'message' => 'Teacher name and phone number are required.'
    ], 400);
}

try {
    /*
     * =========================================================================
     * TODO: Write your custom teacher onboarding logic below.
     * Example:
     * 1. Check if phone already registered in `teacher` table
     * 2. Hash password with password_hash($password, PASSWORD_BCRYPT)
     * 3. Insert into `teacher` table (name, phone, password, subject, created_at)
     * =========================================================================
     */

    // Placeholder response for now
    sendJson([
        'Success' => true,
        'message' => "Onboard Teacher API reached successfully for '{$name}' ({$phone}). You can add your custom database logic in onboard_teacher.php.",
        'data' => [
            'name'    => $name,
            'phone'   => $phone,
            'subject' => $subject
        ]
    ], 200);

} catch (Exception $e) {
    sendJson([
        'Success' => false,
        'message' => 'Error onboarding teacher: ' . $e->getMessage()
    ], 500);
}
