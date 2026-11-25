<?php


require_once "db.php";
require_once "lib.php";




$pdo = db();


function folder_access($pdo, $data)
{

    $student_id = intval($data['sid'] ?? 0);
    $folder_id  = intval($data['fid'] ?? 0);

    if ($student_id <= 0 || $folder_id <= 0) {
        sendJson(["Success" => false, "message" => "Valid student_id and folder_id are required"], 400);
    }

    try {
        $fcsql = "SELECT folder_name FROM lms_folders WHERE folder_id = :fid";
        $fcheck = $pdo->prepare($fcsql);
        $fcheck->execute([':fid' => $folder_id]);
        $vfolder = $fcheck->fetch(PDO::FETCH_ASSOC);

        $stucheck = $pdo->prepare("SELECT name FROM students WHERE id = :id");
        $stucheck->execute([':id' => $student_id]);
        $vstudent = $stucheck->fetch(PDO::FETCH_ASSOC);

        if (!$vstudent || !$vfolder) {
            sendJson(["Success" => false, "message" => "Either folder ID ($folder_id) or student ID ($student_id) is invalid"], 404);
        }

        // Check duplicate entry beforehand
        $dupCheck = $pdo->prepare("SELECT 1 FROM student_folder_access WHERE student_id = ? AND folder_id = ?");
        $dupCheck->execute([$student_id, $folder_id]);
        if ($dupCheck->fetch()) {
            sendJson(["Success" => false, "message" => "Student '{$vstudent['name']}' already has access to '{$vfolder['folder_name']}'"], 400);
        }

        $pdo->beginTransaction();  
        $sql = "INSERT INTO student_folder_access(student_id, folder_id) VALUES (?, ?)";
        $insstmt = $pdo->prepare($sql);
        $insstmt->execute([$student_id, $folder_id]);
        $pdo->commit();

        sendJson([
            "Success" => true,
            "message" => "Folder '{$vfolder['folder_name']}' successfully assigned to student '{$vstudent['name']}'",
            "student_id" => $student_id,
            "folder_id"  => $folder_id
        ], 200);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $e->getMessage();
        if (str_contains($message, "1062 Duplicate entry")) {
            sendJson(["Success" => false, "message" => "Student is already assigned to this folder"], 400);
        } else {
            sendJson(["Success" => false, "message" => $message], 500);
        }
    }
}

// Allow folder_access.php to respond directly if accessed as standalone endpoint
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'folder_access.php') {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [
            'sid' => isset($_POST['student_id']) ? intval($_POST['student_id']) : intval($body['student_id'] ?? 0),
            'fid' => isset($_POST['folder_id']) ? intval($_POST['folder_id']) : intval($body['folder_id'] ?? 0)
        ];
        folder_access($pdo, $data);
        exit;
    }

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list_students';
        if ($action === 'list_students') {
            try {
                $stmt = $pdo->query("
                    SELECT s.id, s.name, s.phone, s.class_id, c.class AS class_name
                    FROM students s
                    LEFT JOIN class c ON c.id = s.class_id
                    ORDER BY s.id DESC
                ");
                sendJson(["Success" => true, "students" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (PDOException $e) {
                sendJson(["Success" => false, "message" => $e->getMessage()], 500);
            }
        }
        exit;
    }
}