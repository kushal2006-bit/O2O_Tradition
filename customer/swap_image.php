<?php
session_start();require_once '../shared/config.php';requireLogin('customer','login.php');$db=getDB();$id=(int)($_GET['id']??0);
$st=$db->prepare("SELECT image_path,status,owner_id FROM swap_listings WHERE id=? LIMIT 1");$st->execute([$id]);$row=$st->fetch();
if(!$row||empty($row['image_path'])||($row['status']!=='active'&&(int)$row['owner_id']!==(int)$_SESSION['customer_id'])){http_response_code(404);exit('Not found');}
$path=__DIR__.'/../uploads/swap/'.basename($row['image_path']);if(!is_file($path)){http_response_code(404);exit('Not found');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit('Not found');}
header('X-Content-Type-Options: nosniff');
header('Content-Type: '.$mime);header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'none\'; script-src \'none\'; frame-ancestors \'none\'; base-uri \'none\';');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private,max-age=300');readfile($path);