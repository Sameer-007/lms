<?php


function add_video(PDO $pdo, array $data){
    if (empty($data['fid']) || empty($data['title']) || empty($data['vurl'])) {
        $error = 'Folder, Video Title, and Video URL are all required.';
        sendJson(['Success'=>false,'Message'=>$error],400);
    } else {
        try {
            // Auto-extract thumbnail if not provided
            if (empty($data['thumb']) || trim($data['thumb']) === '') {
                $data['thumb'] = extract_video_thumbnail($data['vurl']);
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO lms_videos (video_title, video_url, thumbnail_url, folder_id) 
                VALUES (:title, :vurl, :thumb, :fid)");
            $stmt->execute($data);
            $pdo->commit();
            $message = "Video {$data['title']} added successfully!";
            sendJson ([
                'success'  => true,
                'message'  => $message],201);
        } catch (PDOException $e) {
            $error = 'Error adding video: ' . $e->getMessage();
            sendJson(['Success'=>false,'Message'=>$error],400);
        }
    }
    
}


function delete_video(PDO $pdo,int $vid,int $fid)
{
    if($vid>0){
        $vt = $pdo->prepare('select video_title from lms_videos where video_id = ?');
        $vt->execute([$vid]);
        $vtit = $vt->fetchALL(PDO::FETCH_ASSOC);
        if($vtit){
        $stmt = $pdo->prepare("DELETE FROM lms_videos WHERE video_id = ?");
        $stmt->execute([$vid]);
        sendJson(["Success"=>true,"Mesasge"=>"Deleted the video  {$vtit[0]['video_title']} "],200);
        }
        else{
            sendJson(["Success"=>false,"Mesasge"=>"Enter the correct vid"],400);
        }
    }
goback($fid);
}

function update_video(PDO $pdo, string $title, int $fid, int $v_id){
   try {
        $sql = 'update lms_videos set video_title = ? where video_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title,$v_id]);
        $message = "video title for vid {$v_id} updated successfully";
        sendJson(["Success"=>true,"message"=>$message]);
   }
   catch(PDOException $e){
    sendJson(["Success"=>false,"message"=>$e->getMessage()], 500);
   }
}

function move_video(PDO $pdo, int $v_id, int $target_folder_id)
{
    if ($v_id <= 0 || $target_folder_id <= 0) {
        sendJson(['Success' => false, 'message' => 'Valid video_id and destination folder_id are required'], 400);
    }
    try {
        $sql = "UPDATE lms_videos SET folder_id = ? WHERE video_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_folder_id, $v_id]);
        sendJson(['Success' => true, 'message' => 'Video moved successfully!'], 200);
    } catch (PDOException $e) {
        sendJson(['Success' => false, 'message' => $e->getMessage()], 500);
    }
}
