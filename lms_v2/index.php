<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once 'db.php';
require_once 'lib.php';
require_once 'nodes.php';
require_once 'videos.php';
require_once 'folder_access.php';




$pdo=db();
$method=$_SERVER['REQUEST_METHOD'];

$action = isset($_GET['action'])? trim($_GET['action']??''):NULL;
$cf_id = isset($_GET['folder_id'])  ? intval($_GET['folder_id']) : 0;

// Direct browser access without parameters defaults to login page
if ($method === 'GET' && empty($action)) {
    header("Location: login.html");
    exit;
}

switch(true){
    //
    case ( $method === 'GET' && $action === 'get_tree'):
        getnodes($pdo,$cf_id);
        break;

    case ($method === 'GET' && $action=== 'student_login'):
        $stu_id = isset($_GET['stu_id']) ? intval($_GET['stu_id']): 0;
        getsubjects($pdo,$stu_id);
        break;
    case ($method === 'POST' && $action === 'create_folder'):
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $folder_name      = isset($_POST['folder_name']) ? trim($_POST['folder_name']) : (isset($body['folder_name']) ? trim($body['folder_name']) : NULL);
        $thumbnail_url    = isset($_POST['thumbnail_url']) ? trim($_POST['thumbnail_url']) : ($body['thumbnail_url'] ?? NULL);
        $parent_folder_id = isset($_POST['parent_folder_id']) ? intval($_POST['parent_folder_id']) : (isset($body['parent_folder_id']) ? intval($body['parent_folder_id']) : NULL);
        $data = [ 'name' => $folder_name, 'thumb' => $thumbnail_url, 'parent' => $parent_folder_id ];
        post_nodes($pdo, $data);
        break;

    case ($method === 'POST' && $action === 'add_video'):
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $title          = isset($_POST['title']) ? trim($_POST['title']) : ($body['title'] ?? NULL);
        $thumbnail_url  = isset($_POST['thumbnail_url']) ? trim($_POST['thumbnail_url']) : ($body['thumbnail_url'] ?? NULL);
        $folder_id      = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : (isset($body['folder_id']) ? intval($body['folder_id']) : $cf_id);
        $video_url      = isset($_POST['video_url']) ? trim($_POST['video_url']) : ($body['video_url'] ?? NULL);
        $data = [ 
            'title' => $title,
            'thumb' => $thumbnail_url,
            'fid'   => $folder_id,
            'vurl'  => $video_url 
        ];
        add_video($pdo, $data);
        break;

    case ($method === 'POST' && $action === 'Student_access'):
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [
           'sid' => isset($_POST['student_id']) ? intval($_POST['student_id']) : intval($body['student_id'] ?? 0),
           'fid' => isset($_POST['folder_id']) ? intval($_POST['folder_id']) : intval($body['folder_id'] ?? 0)
        ];
        folder_access($pdo, $data);
        break;

    case ($method === 'GET' && $action === 'list_classes'):
        try {
            $stmt = $pdo->query("SELECT id, class, folder_id FROM class ORDER BY id ASC");
            sendJson(["Success" => true, "classes" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            sendJson(["Success" => false, "message" => $e->getMessage()], 500);
        }
        break;

    case ($method === 'GET' && $action === 'list_all_folders'):
        try {
            $stmt = $pdo->query("SELECT folder_id, folder_name, parent_folder_id FROM lms_folders WHERE PARENT_FOLDER_ID IS NULL ORDER BY parent_folder_id  ASC, folder_name ASC");
            sendJson(["Success" => true, "folders" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            sendJson(["Success" => false, "message" => $e->getMessage()], 500);
        }
        break;

    case ($method === 'GET' && $action === 'list_students'):
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
        break;

    case ($method === 'GET' && $action === 'list_enrollments'):
        try {
            $sql = "
                SELECT sfa.student_id, s.name AS student_name, s.phone,
                       sfa.folder_id, f.folder_name
                FROM student_folder_access sfa
                INNER JOIN students s ON s.id = sfa.student_id
                INNER JOIN lms_folders f ON f.folder_id = sfa.folder_id
                ORDER BY sfa.student_id DESC, sfa.folder_id ASC
                LIMIT 100
            ";
            $stmt = $pdo->query($sql);
            sendJson(["Success" => true, "enrollments" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            sendJson(["Success" => false, "message" => $e->getMessage()], 500);
        }
        break;

    case ($method === 'DELETE' && $action ==='folder'):
        delete_node($pdo, $cf_id);
        break;

    case ($method === 'POST' && $action === 'request_delete_folder'):
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fid = intval($_POST['folder_id'] ?? $body['folder_id'] ?? $cf_id);
        $teacher_id = intval($_POST['teacher_id'] ?? $body['teacher_id'] ?? 0);
        $teacher_name = trim($_POST['teacher_name'] ?? $body['teacher_name'] ?? '');
        $reason = trim($_POST['reason'] ?? $body['reason'] ?? '');

        if ($fid <= 0) {
            sendJson(["Success" => false, "message" => "Valid folder_id is required."], 400);
            exit;
        }

        try {
            // Check folder existence
            $fStmt = $pdo->prepare("SELECT folder_name FROM lms_folders WHERE folder_id = ?");
            $fStmt->execute([$fid]);
            $folder = $fStmt->fetch(PDO::FETCH_ASSOC);

            if (!$folder) {
                sendJson(["Success" => false, "message" => "Folder not found or already deleted."], 404);
                exit;
            }

            // Check if already pending
            $chkStmt = $pdo->prepare("SELECT id FROM folder_delete_requests WHERE folder_id = ? AND status = 'pending'");
            $chkStmt->execute([$fid]);
            if ($chkStmt->fetch()) {
                sendJson(["Success" => false, "message" => "A deletion request for this folder is already pending Admin approval."], 409);
                exit;
            }

            // Insert request
            $insStmt = $pdo->prepare("
                INSERT INTO folder_delete_requests (folder_id, folder_name, teacher_id, teacher_name, reason, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $insStmt->execute([$fid, $folder['folder_name'], $teacher_id, $teacher_name, $reason]);

            sendJson([
                "Success" => true,
                "message" => "Deletion request for folder '{$folder['folder_name']}' has been submitted to Admin for approval."
            ], 201);
        } catch (PDOException $e) {
            sendJson(["Success" => false, "message" => $e->getMessage()], 500);
        }
        break;

    case ($method === 'GET' && $action === 'list_delete_requests'):
        try {
            $stmt = $pdo->query("
                SELECT r.id, r.folder_id, r.folder_name, r.teacher_id, r.teacher_name, r.status, r.reason, r.created_at,
                       f.folder_id AS current_folder_exists
                FROM folder_delete_requests r
                LEFT JOIN lms_folders f ON f.folder_id = r.folder_id
                ORDER BY (r.status = 'pending') DESC, r.id DESC
                LIMIT 100
            ");
            sendJson(["Success" => true, "requests" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            sendJson(["Success" => false, "message" => $e->getMessage()], 500);
        }
        break;

    case ($method === 'POST' && $action === 'approve_delete_folder'):
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $reqId = intval($_POST['request_id'] ?? $body['request_id'] ?? 0);

        if ($reqId <= 0) {
            sendJson(["Success" => false, "message" => "Valid request_id is required."], 400);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM folder_delete_requests WHERE id = ?");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                sendJson(["Success" => false, "message" => "Delete request not found."], 404);
                exit;
            }

            if ($req['status'] !== 'pending') {
                sendJson(["Success" => false, "message" => "This request has already been {$req['status']}."], 400);
                exit;
            }

            $targetFolderId = intval($req['folder_id']);

            // Execute folder deletion
            $pdo->beginTransaction();
            $recursive_id = get_recursive_id();
            
            // Delete child videos and child folders
            $vStmt = $pdo->prepare("DELETE FROM lms_videos WHERE folder_id IN " . $recursive_id);
            $vStmt->execute(['parent' => $targetFolderId]);

            $fStmt = $pdo->prepare("DELETE FROM lms_folders WHERE folder_id IN " . $recursive_id);
            $fStmt->execute(['parent' => $targetFolderId]);

            // Delete target folder videos & folder itself
            $vTarget = $pdo->prepare("DELETE FROM lms_videos WHERE folder_id = ?");
            $vTarget->execute([$targetFolderId]);

            $fTarget = $pdo->prepare("DELETE FROM lms_folders WHERE folder_id = ?");
            $fTarget->execute([$targetFolderId]);

            // Mark request approved
            $upd = $pdo->prepare("UPDATE folder_delete_requests SET status = 'approved' WHERE id = ?");
            $upd->execute([$reqId]);

            $pdo->commit();

            sendJson([
                "Success" => true,
                "message" => "Folder '{$req['folder_name']}' approved and permanently deleted."
            ], 200);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendJson(["Success" => false, "message" => $e->getMessage()], 500);
        }
        break;

    case ($method === 'POST' && $action === 'reject_delete_folder'):
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $reqId = intval($_POST['request_id'] ?? $body['request_id'] ?? 0);

        if ($reqId <= 0) {
            sendJson(["Success" => false, "message" => "Valid request_id is required."], 400);
            exit;
        }

        try {
            $upd = $pdo->prepare("UPDATE folder_delete_requests SET status = 'rejected' WHERE id = ? AND status = 'pending'");
            $upd->execute([$reqId]);

            if ($upd->rowCount() > 0) {
                sendJson(["Success" => true, "message" => "Folder deletion request was rejected."], 200);
            } else {
                sendJson(["Success" => false, "message" => "Request not found or not in pending state."], 404);
            }
        } catch (PDOException $e) {
            sendJson(["Success" => false, "message" => $e->getMessage()], 500);
        }
        break;

    case ($method === 'DELETE' && $action === 'video'):
        $v_id = isset($_GET['vid']) ? intval($_GET['vid']) : 0;
        delete_video($pdo, $v_id, $cf_id);
        break;

    case ($method === 'PUT' && $action === 'update_folder'):
        $rawInput = file_get_contents('php://input');
        $_PUT = json_decode($rawInput, true);
        $folder_name = isset($_PUT['folder_name']) ? trim($_PUT['folder_name'] ?? '') : NULL;
        update_nodes($pdo, $folder_name, $cf_id);
        break;

    case ($method === 'PUT' && $action === 'move_folder'):
        $rawInput = file_get_contents('php://input');
        $_PUT = json_decode($rawInput, true) ?? [];
        $parent_id = isset($_PUT['parent_folder_id']) ? intval($_PUT['parent_folder_id']) : (isset($_GET['parent_folder_id']) ? intval($_GET['parent_folder_id']) : null);
        move_node($pdo, $cf_id, $parent_id);
        break;

    case ($method === 'PUT' && $action === 'update_video'):
        $rawInput = file_get_contents('php://input');
        $_PUT = json_decode($rawInput, true);
        $title = isset($_PUT['title']) ? trim($_PUT['title'] ?? '') : NULL;
        $v_id = isset($_PUT['video_id']) ? intval($_PUT['video_id']) : 0;
        update_video($pdo, $title, $cf_id, $v_id);
        break;

    case ($method === 'PUT' && $action === 'move_video'):
        $rawInput = file_get_contents('php://input');
        $_PUT = json_decode($rawInput, true) ?? [];
        $v_id = isset($_PUT['video_id']) ? intval($_PUT['video_id']) : (isset($_GET['vid']) ? intval($_GET['vid']) : 0);
        $target_fid = isset($_PUT['target_folder_id']) ? intval($_PUT['target_folder_id']) : (isset($_GET['target_folder_id']) ? intval($_GET['target_folder_id']) : $cf_id);
        move_video($pdo, $v_id, $target_fid);
        break;
    default:
        sendJson(["Success"=>false,"Message"=> "method not allowed"],400);
        break;
}



function getsubjects($pdo, $id)
{
    try {
        if ($id <= 0) {
            sendJson(["Success" => false, "message" => "Valid student ID is required"], 400);
        }

        // 1. Fetch student info
        $st = $pdo->prepare("SELECT id, name, class_id FROM students WHERE id = ?");
        $st->execute([$id]);
        $student = $st->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            sendJson(["Success" => false, "message" => "Student with ID {$id} not found"], 404);
        }

        // 2. Fetch all folders/classes:
        //    Explicitly assigned in student_folder_access OR implicitly assigned via student's class (class.folder_id)
        $sql = "
            SELECT DISTINCT f.folder_id, f.folder_name, f.thumbnail_url, f.parent_folder_id
            FROM lms_folders f
            LEFT JOIN student_folder_access sfa ON f.folder_id = sfa.folder_id AND sfa.student_id = :sid1
            LEFT JOIN students s ON s.id = :sid2
            LEFT JOIN class c ON c.id = s.class_id AND c.folder_id = f.folder_id
            WHERE sfa.folder_id IS NOT NULL OR c.folder_id IS NOT NULL
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sid1' => $id, ':sid2' => $id]);
        $assigned_folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Fetch subjects / subfolders inside the assigned classes
        $subjects = [];
        if (!empty($assigned_folders)) {
            $parent_ids = array_column($assigned_folders, 'folder_id');
            $placeholders = implode(',', array_fill(0, count($parent_ids), '?'));
            $stmt1 = $pdo->prepare("
                SELECT folder_id, folder_name, parent_folder_id, thumbnail_url 
                FROM lms_folders 
                WHERE parent_folder_id IN ($placeholders)
            ");
            $stmt1->execute($parent_ids);
            $subjects = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        }

        sendJson([
            "Success"          => true,
            "student"          => $student,
            "assigned_folders" => $assigned_folders,
            "subjects"         => $subjects
        ], 200);

    } catch (PDOException $e) {
        sendJson(["Success" => false, "message" => $e->getMessage()], 500);
    }
}




?>