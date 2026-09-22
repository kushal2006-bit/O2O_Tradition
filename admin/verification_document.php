<?php
session_start();
require_once '../shared/config.php';

if(!isset($_SESSION['admin_id'])){
    http_response_code(403);
    exit('Forbidden');
}

$db=getDB();
$id=(int)($_GET['id']??0);

$st=$db->prepare("SELECT document_path FROM vendor_verifications WHERE id=? LIMIT 1");
$st->execute([$id]);
$row=$st->fetch();

if(!$row||empty($row['document_path'])){
    http_response_code(404);
    exit('Not found');
}

$path=__DIR__.'/../uploads/vendor-verification/'.basename($row['document_path']);
if(!is_file($path)){
    http_response_code(404);
    exit('Not found');
}

$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
$allowed=['application/pdf','image/jpeg','image/png'];

if(!in_array($mime,$allowed,true)){
    http_response_code(404);
    exit('Not found');
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="'.basename($path).'"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
?>