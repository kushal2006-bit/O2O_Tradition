<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer','login.php');

$db=getDB();
$customerId=(int)$_SESSION['customer_id'];
$requestId=(int)($_GET['id']??0);

$st=$db->prepare("SELECT result_image FROM tryon_requests WHERE id=? AND customer_id=? AND status='completed' LIMIT 1");
$st->execute([$requestId,$customerId]);
$row=$st->fetch();

if(!$row||empty($row['result_image'])){
    http_response_code(404);
    exit('Not found');
}

$path=__DIR__.'/../uploads/tryon/'.$row['result_image'];
if(!is_file($path)){
    http_response_code(404);
    exit('Not found');
}

$finfo=new finfo(FILEINFO_MIME_TYPE);
$mime=$finfo->file($path);
$allowed=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
if(!isset($allowed[$mime])){
    http_response_code(404);
    exit('Not found');
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
?>