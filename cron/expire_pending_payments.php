<?php
require_once __DIR__.'/../shared/config.php';
require_once __DIR__.'/../shared/payment_lifecycle.php';

$db=getDB();
$expired=o2oExpirePendingPayments($db);
echo "Expired {$expired} pending payment attempt(s).\n";
?>