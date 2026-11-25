<?php
/**
 * Onboard Student API (LMS v2)
 * Registers a student and implicitly assigns folder access based on the class folder_id.
 */
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: Provide classes list with their linked folder_id
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, class, folder_id FROM class ORDER BY id ASC");
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJson([
            'Success' => true,
            'classes' => $classes
        ], 200);
    } catch (PDOException $e) {
        sendJson(['Success' => false, 'message' => $e->getMessage()], 500);
    }
    exit;
}

// Handle POST: Onboard Student
if ($method === 'POST') {
    $raw = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $name     = trim($_POST['name'] ?? $raw['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? $raw['phone'] ?? '');
    $password = trim($_POST['password'] ?? $raw['password'] ?? '');
    $class_id = intval($_POST['class_id'] ?? $raw['class_id'] ?? $_POST['class'] ?? $raw['class'] ?? 0);
    $admin_id = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : (isset($raw['admin_id']) ? intval($raw['admin_id']) : null);

    if (empty($name)) {
        sendJson(['Success' => false, 'message' => 'Student name is required.'], 400);
        exit;
    }
    if (empty($phone)) {
        sendJson(['Success' => false, 'message' => 'Phone number is required.'], 400);
        exit;
    }
    if (empty($password)) {
        sendJson(['Success' => false, 'message' => 'Password is required.'], 400);
        exit;
    }
    if ($class_id <= 0) {
        sendJson(['Success' => false, 'message' => 'Please select a valid Class for the student.'], 400);
        exit;
    }

    try {
        // 1. Check if class exists and get its linked folder_id
        $classStmt = $pdo->prepare("SELECT id, class, folder_id FROM class WHERE id = ?");
        $classStmt->execute([$class_id]);
        $classData = $classStmt->fetch(PDO::FETCH_ASSOC);

        if (!$classData) {
            sendJson(['Success' => false, 'message' => "Selected Class (ID: {$class_id}) does not exist."], 404);
            exit;
        }

        // 2. Check if phone number is already registered
        $checkPhone = $pdo->prepare("SELECT id, name FROM students WHERE phone = ?");
        $checkPhone->execute([$phone]);
        if ($existing = $checkPhone->fetch(PDO::FETCH_ASSOC)) {
            sendJson([
                'Success' => false,
                'message' => "A student with phone '{$phone}' is already registered (ID: {$existing['id']} - {$existing['name']})."
            ], 409);
            exit;
        }

        // 3. Prepare student fields
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $expiryDate = date('Y-m-d', strtotime('+1 year'));
        $role = 'student';
        $content = 0;

        $pdo->beginTransaction();

        // 4. Insert into students table
        $insStudent = $pdo->prepare("
            INSERT INTO students (name, phone, password, expiryDate, role, content, class_id, admin_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStudent->execute([
            $name,
            $phone,
            $hashedPassword,
            $expiryDate,
            $role,
            $content,
            $class_id,
            $admin_id
        ]);
        $student_id = (int)$pdo->lastInsertId();

        // 5. Implicitly grant folder access using class folder_id
        $folder_id = !empty($classData['folder_id']) ? intval($classData['folder_id']) : null;
        $folderName = null;

        if ($folder_id) {
            // Verify folder exists in lms_folders
            $fStmt = $pdo->prepare("SELECT folder_name FROM lms_folders WHERE folder_id = ?");
            $fStmt->execute([$folder_id]);
            $folderRow = $fStmt->fetch(PDO::FETCH_ASSOC);
            $folderName = $folderRow ? $folderRow['folder_name'] : "Folder #{$folder_id}";

            // Insert into student_folder_access
            $accessStmt = $pdo->prepare("
                INSERT INTO student_folder_access (student_id, folder_id)
                VALUES (?, ?)
            ");
            $accessStmt->execute([$student_id, $folder_id]);
        }

        $pdo->commit();

        $accessMsg = $folder_id 
            ? " and automatically granted access to '{$folderName}' (Folder ID: {$folder_id})"
            : "";

        sendJson([
            'Success' => true,
            'message' => "Student '{$name}' onboarded successfully{$accessMsg}!",
            'student' => [
                'id'         => $student_id,
                'name'       => $name,
                'phone'      => $phone,
                'class_id'   => $class_id,
                'class_name' => $classData['class'],
                'folder_id'  => $folder_id,
                'folder_name'=> $folderName
            ]
        ], 201);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJson(['Success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJson(['Success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}
