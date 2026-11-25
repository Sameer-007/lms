<?php
function sendJson(array $data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
}

function get_folders(PDO $pdo){
    $result = '';
    try{
       $folders = $pdo->query("SELECT folder_id, folder_name FROM full_path ")->fetchAll();
       return $folders;
    }
    catch(PDOException $e){
        sendJson(['Success'=>false,'message'=>$e->getMessage()],500);
    }
}

function goback(int $id){
    if ($id !== null) {
    header("Location: index.php?folder_id=" . $id);
} else {
    header("Location: index.php");
}
exit;
}

function get_parent(PDO $pdo,int $id){
    try {
        $stmt = $pdo->prepare('select parent_folder_id from lms_folders where folder_id = ?');
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        if($res){
        return $res['parent_folder_id'];
        }
        else{ 
            return 0;
        }
    }
    catch(PDOException $e){
          sendJson(['Success'=>false,'message'=>$e->getMessage()],500);
    }
}


function get_foldername(PDO $pdo,int $cf_id)
{
    try{
        $sql = 'select folder_name from lms_folders where folder_id = ?';
        $stmt= $pdo->prepare($sql);
        $stmt->execute([$cf_id]);
        $res= $stmt->fetch()['folder_name'];

       if( isset($res)){
        return $res;}
        else{
             return null;
        }
    }catch(PDOException $e){
        sendJson(['success'=>true,'message'=>$e->getmessage()],200);
    }
}

/**
 * Automatically extracts thumbnail URL from video URL (YouTube, Vimeo, etc.)
 * if explicit thumbnail_url is null or empty.
 */
function extract_video_thumbnail(?string $video_url, ?string $thumbnail_url = null): ?string {
    if (!empty($thumbnail_url) && trim($thumbnail_url) !== '') {
        return trim($thumbnail_url);
    }
    if (empty($video_url)) {
        return null;
    }
    $url = trim($video_url);

    // YouTube regex: watch?v=ID, youtu.be/ID, embed/ID, shorts/ID, live/ID, v/ID
    if (preg_match('~(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts|live)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})~i', $url, $m)) {
        return "https://img.youtube.com/vi/{$m[1]}/mqdefault.jpg";
    }

    // Vimeo regex: vimeo.com/ID
    if (preg_match('~vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/[^\/]*\/videos\/|album\/\d+\/video\/|video\/|)(\d+)~i', $url, $m)) {
        return "https://vumbnail.com/{$m[1]}.jpg";
    }

    return null;
}