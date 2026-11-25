<?php
/**
 * ac.php - High Performance Permission Validator
 * Optimized with fast-path index lookup and targeted upward ancestor CTE.
 */
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'lib.php';

$pdo = db();

// Support both POST, GET, and JSON input
$rawInput = json_decode(file_get_contents('php://input'), true) ?? [];
$student_id = intval($_POST['student_id'] ?? $rawInput['student_id'] ?? $_GET['student_id'] ?? 0);
$folder_id  = intval($_POST['folder_id']  ?? $rawInput['folder_id']  ?? $_GET['folder_id']  ?? 0);

if ($student_id <= 0 || $folder_id <= 0) {
    sendJson([
        "Success" => false,
        "message" => "Both student_id and folder_id are required and must be valid integers."
    ], 400);
    exit;
}

try {
    // 1. FAST-PATH: Direct Root / Assigned Check (Ultra-fast index hit, ~1-2ms on DB)
    // Directly checks if folder_id is explicitly assigned OR is the student's base class folder
    $fastSql = "
        SELECT 1
        FROM students s
        LEFT JOIN class c ON c.id = s.class_id
        LEFT JOIN student_folder_access sfa ON sfa.student_id = s.id AND sfa.folder_id = :fid1
        WHERE s.id = :sid AND (sfa.folder_id IS NOT NULL OR c.folder_id = :fid2)
        LIMIT 1
    ";
    $fastStmt = $pdo->prepare($fastSql);
    $fastStmt->execute([
        ':sid'  => $student_id,
        ':fid1' => $folder_id,
        ':fid2' => $folder_id
    ]);

    if ($fastStmt->fetchColumn()) {
        sendJson(["Success" => true, "message" => "Permission granted (direct)"], 200);
        exit;
    }

    // 2. TARGETED UPWARD ANCESTOR CHECK:
    // Walks up ONLY from $folder_id to root (touches only 2-4 indexed primary keys: topic -> chapter -> subject -> class)
    // and checks if any ancestor matches the student's assigned folders.
    $ancestorSql = "
        WITH RECURSIVE ancestors AS (
            SELECT folder_id, parent_folder_id
            FROM lms_folders
            WHERE folder_id = :folder_id
            UNION ALL
            SELECT f.folder_id, f.parent_folder_id
            FROM lms_folders f
            INNER JOIN ancestors a ON f.folder_id = a.parent_folder_id
        )
        SELECT 1
        FROM ancestors a
        WHERE a.folder_id IN (
            SELECT sfa.folder_id
            FROM student_folder_access sfa
            WHERE sfa.student_id = :sid1
            UNION
            SELECT c.folder_id
            FROM students s
            INNER JOIN class c ON c.id = s.class_id
            WHERE s.id = :sid2 AND c.folder_id IS NOT NULL
        )
        LIMIT 1
    ";

    $stmt = $pdo->prepare($ancestorSql);
    $stmt->execute([
        ':folder_id' => $folder_id,
        ':sid1'      => $student_id,
        ':sid2'      => $student_id
    ]);

    $hasAccess = (bool) $stmt->fetchColumn();

    if ($hasAccess) {
        sendJson(["Success" => true, "message" => "Permission granted"], 200);
    } else {
        sendJson(["Success" => false, "message" => "Permission denied: You do not have access to this folder."], 403);
    }

} catch (PDOException $e) {
    sendJson(["Success" => false, "message" => $e->getMessage()], 500);
}
