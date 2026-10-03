<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
require_once __DIR__.'/../shared/config.php';
require_once __DIR__.'/../shared/payment_lifecycle.php';

$db=getDB();
$expired=o2oExpirePendingPayments($db);
echo "Expired {$expired} pending payment attempt(s).\n";
?>