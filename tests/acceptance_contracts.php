<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$failures=[];

function acceptanceFile(string $path):string{
    global $root,$failures;
    $full=$root.'/'.$path;
    if(!is_file($full)){$failures[]="Missing acceptance flow file: {$path}";return '';}
    return (string)file_get_contents($full);
}
function acceptanceText(string $path,string $needle):void{
    global $failures;
    $content=acceptanceFile($path);
    if($content!==''&&strpos($content,$needle)===false)$failures[]="Acceptance contract missing in {$path}: {$needle}";
}
function acceptanceRoute(string $path):void{acceptanceFile($path);}

/*
 * Deployment-independent acceptance gate.
 * This validates that every critical customer/vendor/admin journey has its
 * expected entrypoint and backend state transition. Live browser, MySQL,
 * Razorpay and external-AI execution remain target-environment tests.
 */

$routes=[
 'customer/login.php','customer/home.php','customer/order.php','customer/buy.php',
 'customer/sell.php','customer/sell_buy.php','customer/swap.php','customer/swap_request.php',
 'customer/orders.php','customer/rental_tracking.php','customer/payment.php',
 'customer/payment_callback.php','customer/payment_webhook.php','customer/notifications.php',
 'customer/stylist.php','customer/tryon.php','customer/assistant.php','customer/complete_look.php',
 'vendor/login.php','vendor/dashboard.php','vendor/sell_listings.php','vendor/condition_ai.php',
 'vendor/trust_records.php','vendor/reviews.php','vendor/notifications.php',
 'admin/login.php','admin/dashboard.php','admin/refunds.php','admin/complaints.php',
 'admin/moderation.php','admin/verifications.php','admin/deletion_requests.php','admin/analytics.php'
];
foreach($routes as $route)acceptanceRoute($route);

$contracts=[
 ['customer/order.php',"o2oCreateRazorpayOrder"],
 ['customer/order.php',"INSERT INTO orders"],
 ['customer/order.php',"o2oNotifyVendor"],
 ['customer/buy.php',"INSERT INTO purchase_orders"],
 ['customer/buy.php',"INSERT INTO purchase_order_items"],
 ['customer/buy.php',"UPDATE items SET available=0"],
 ['customer/sell.php',"pending_review"],
 ['customer/sell_buy.php',"o2oConsumeRewardCredits"],
 ['customer/swap_request.php',"INSERT INTO swap_requests"],
 ['customer/swap_request.php',"o2oNotifyCustomer"],
 ['customer/payment_callback.php',"o2oVerifyRazorpaySignature"],
 ['customer/payment_callback.php',"Payment amount, currency, or capture status could not be verified."],
 ['customer/payment_webhook.php',"HTTP_X_RAZORPAY_SIGNATURE"],
 ['customer/payment_webhook.php',"payment_webhook_events"],
 ['customer/payment_webhook.php',"refund.processed"],
 ['customer/payment_webhook.php',"refund.failed"],
 ['customer/rental_tracking.php',"rental_tracking_events"],
 ['customer/assistant.php',"'role'=>'system'"],
 ['customer/tryon.php',"tryon_requests"],
 ['customer/stylist.php',"mode"],
 ['customer/complete_look.php',"Book rental"],
 ['customer/complete_look.php',"Buy outfit"],
 ['vendor/dashboard.php',"payment_status"],
 ['vendor/sell_listings.php',"o2oNotifyCustomer"],
 ['vendor/condition_ai.php',"status='processing'"],
 ['vendor/trust_records.php',"Completed sanitization requires a returned and inspected rental"],
 ['vendor/reviews.php',"Save Response"],
 ['admin/refunds.php',"refund_status='created'"],
 ['admin/complaints.php',"complaint_"],
 ['admin/moderation.php',"o2oNotifyCustomer"],
 ['admin/verifications.php',"o2oNotifyVendor"],
 ['admin/deletion_requests.php',"Approve & Anonymize"],
 ['admin/analytics.php',"Completed rental and delivered purchase activity"],
 ['shared/notifications.php',"o2oMailFrom();"],
 ['shared/payments.php',"is_finite($amount)"],
 ['database/migrate.php',"GET_LOCK('o2o_tradition_migrations',10)"]
];
foreach($contracts as [$path,$needle])acceptanceText($path,$needle);

if($failures){
    fwrite(STDERR,"Acceptance contract gate failed:\n- ".implode("\n- ",$failures)."\n");
    exit(1);
}
echo "Deployment-independent acceptance contract gate passed for ".count($routes)." routes and ".count($contracts)." critical contracts.\n";
echo "Live browser/database/gateway/external-service acceptance still requires the target deployment environment.\n";
