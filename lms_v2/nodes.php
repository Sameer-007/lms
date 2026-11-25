<?php


function post_nodes(PDO $pdo, array $data){
    if (isset($data['name'])){
        $sql = "INSERT into lms_folders (folder_name,parent_folder_id,thumbnail_url) values( :name,:parent,:thumb)";
        try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['name'],$data['parent'],$data['thumb']]);
        $pdo->commit();
        sendJson(['Success'=>true,'message'=>"Folder {$data['name']} created successfully!"],201);
    } catch (PDOException $e) {

        sendJson(['Success'=>false,'Error message'=>$e->getMessage()],400);
    }
    }
    else{
        sendJson(['Success'=>false,'Error message'=>'folder_name cannot be null'],400);
    }
}

function delete_node(PDO $pdo, int $cf_id)
{
    if ($cf_id > 0) {
        $recursive_id = get_recursive_id();
        $videoSql = "DELETE FROM lms_videos WHERE folder_id IN " . $recursive_id;
        $folderSql = "DELETE FROM lms_folders WHERE folder_id IN " . $recursive_id;
        try {
            $stmt = $pdo->prepare($videoSql);
            $stmt->execute(['parent' => $cf_id]);

            $stmt = $pdo->prepare($folderSql);
            $stmt->execute(['parent' => $cf_id]);

            // Also delete videos in the target folder itself
            $stmt = $pdo->prepare("DELETE FROM lms_videos WHERE folder_id = ?");
            $stmt->execute([$cf_id]);

            // Delete the folder itself
            $stmt = $pdo->prepare("DELETE FROM lms_folders WHERE folder_id = ?");
            $stmt->execute([$cf_id]);

            sendJson(["Success" => true, "message" => "Folder and contents deleted successfully"], 200);
        } catch (PDOException $e) {
            sendJson(['Success' => false, "message" => $e->getMessage()], 500);
        }
    } else {
        sendJson(["Success" => false, "message" => "Invalid folder_id"], 400);
    }
}

function move_node(PDO $pdo, int $cf_id, $target_parent_id)
{
    if ($cf_id <= 0) {
        sendJson(['Success' => false, 'message' => 'Valid folder_id is required'], 400);
    }

    $target_parent = ($target_parent_id !== null && intval($target_parent_id) > 0) ? intval($target_parent_id) : null;

    if ($cf_id === $target_parent) {
        sendJson(['Success' => false, 'message' => 'Cannot move a folder into itself'], 400);
    }

    try {
        $sql = "UPDATE lms_folders SET parent_folder_id = ? WHERE folder_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_parent, $cf_id]);
        sendJson(['Success' => true, 'message' => 'Folder moved successfully!'], 200);
    } catch (PDOException $e) {
        sendJson(['Success' => false, 'message' => $e->getMessage()], 500);
    }
}


function getnodes(PDO $pdo,int $cf_id){

    if($cf_id === 0){

       $sql = "
           SELECT f.*, 
                  (SELECT id FROM folder_delete_requests WHERE folder_id = f.folder_id AND status = 'pending' LIMIT 1) AS delete_pending
           FROM lms_folders f
           WHERE f.parent_folder_id IS NULL
       ";
        try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $videos = $pdo->query("select * from lms_videos where folder_id is NULL")->fetchALL(PDO::FETCH_ASSOC);
        foreach ($videos as &$v) {
            if (empty($v['thumbnail_url']) && !empty($v['video_url'])) {
                $v['thumbnail_url'] = extract_video_thumbnail($v['video_url']);
            }
        }
        unset($v);
        sendJson(['Success'=>true,'folders'=>$result,'Videos'=>$videos],200);
    } catch (PDOException $e) {

        sendJson(['Success'=>true,'Error message'=>$e->getMessage()],400);
    }
    }else {
     $sql = "
         SELECT f.*, 
                (SELECT id FROM folder_delete_requests WHERE folder_id = f.folder_id AND status = 'pending' LIMIT 1) AS delete_pending
         FROM lms_folders f 
         WHERE f.parent_folder_id = :pfid
     ";
     $sql1 = 'select * from lms_videos v where folder_id = ?';
    try {
        $stmt = $pdo->prepare($sql);
         $stmt->execute([$cf_id]);
        $folder = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt2 = $pdo->prepare($sql1);
        $stmt2->execute([$cf_id]);
        $videos = $stmt2->fetchALL(PDO::FETCH_ASSOC);
        foreach ($videos as &$v) {
            if (empty($v['thumbnail_url']) && !empty($v['video_url'])) {
                $v['thumbnail_url'] = extract_video_thumbnail($v['video_url']);
            }
        }
        unset($v);
        if($folder || $videos){
        sendJson(['Success'=>true,'folders'=>$folder, 'videos'=>$videos],200);
        }
        if(!$folder && !$videos){
           sendJson(['Success'=>true,'folders'=>'no folders & videos found'],200);
        }
    } catch (PDOException $e) {

        sendJson(['Success'=>true,'Error message'=>$e->getMessage()],400);
    }
    }
    
}



function update_nodes(PDO $pdo, $newname, int $cf_id)
{
    if (isset($newname) && trim($newname) !== '') {
        $newname = trim($newname);
        $success = false;
        $message = "";
        try {
            $p_id = get_parent($pdo, $cf_id);
            $folder_name = get_foldername($pdo, $cf_id);

            if ($p_id) {
                $check = $pdo->prepare('SELECT folder_name FROM lms_folders WHERE folder_name = ? AND parent_folder_id = ?');
                $check->execute([$newname, $p_id]);
            } else {
                $check = $pdo->prepare('SELECT folder_name FROM lms_folders WHERE folder_name = ? AND parent_folder_id IS NULL');
                $check->execute([$newname]);
            }
            $rceck = $check->fetch();

            if ($rceck && $rceck['folder_name'] === $newname && $newname === $folder_name) {
                $success = false;
                $message = "Please enter a new name, not the existing folder name.";
            } else if ($rceck && $rceck['folder_name'] === $newname) {
                $success = false;
                $message = "Please choose another name; folder '{$newname}' is already present.";
            } else {
                $sql = "UPDATE lms_folders SET folder_name = ? WHERE folder_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newname, $cf_id]);
                $message = "'{$newname}' updated successfully";
                $success = true;
            }
            sendJson(['success' => $success, 'message' => $message], 200);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'message' => $e->getMessage()], 500);
        }
    } else {
        sendJson(["Success" => false, 'message' => 'Folder new name cannot be empty'], 400);
    }
}



