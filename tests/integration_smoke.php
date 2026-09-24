<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function requireFile(string $path): string {
    global $root, $failures;
    $full = $root.'/'.$path;
    if (!is_file($full)) {
        $failures[] = "Missing required file: {$path}";
        return '';
    }
    return (string)file_get_contents($full);
}
function requireText(string $path, string $needle): void {
    global $failures;
    $content = requireFile($path);
    if ($content !== '' && strpos($content, $needle) === false) {
        $failures[] = "Expected integration contract not found in {$path}: {$needle}";
    }
}

requireText('customer/order.php', "require_once '../shared/rewards.php';");
requireText('customer/order.php', "o2oConsumeRewardCredits");
requireText('customer/order.php', "o2oNotifyVendor");
requireText('customer/order.php', "o2oCreateRazorpayOrder");
requireText('customer/buy.php', "o2oCreateRazorpayOrder");
requireText('customer/payment.php', "checkout.razorpay.com");
requireText('customer/payment_callback.php', "o2oVerifyRazorpaySignature");
requireText('customer/payment_webhook.php', "HTTP_X_RAZORPAY_SIGNATURE");
requireText('shared/payments.php', "api.razorpay.com/v1/orders");
requireText('customer/buy.php', "product_size_id");
requireText('customer/buy.php', "o2oConsumeRewardCredits");
requireText('customer/buy.php', "o2oNotifyVendor");
requireText('customer/sell_buy.php', "o2oConsumeRewardCredits");
requireText('customer/swap_requests.php', "o2oAwardReward");
requireText('vendor/dashboard.php', "o2oAwardReward");
requireText('vendor/sell_listings.php', "o2oNotifyCustomer");
requireText('customer/rental_tracking.php', "rental_tracking_events");
requireText('vendor/dashboard.php', "o2oRentalRecordEvent");
requireText('vendor/dashboard.php', "payment_status");
requireText('vendor/trust_records.php', "o2oRentalRecordEvent");
requireText('shared/rental_tracking.php', "INSERT IGNORE INTO rental_tracking_events");
requireText('shared/rental_tracking.php', "'picked_up' => \$status === 'in_progress'");
requireText('shared/rental_tracking.php', "'returned' => \$status === 'completed'");
requireText('shared/rental_tracking.php', "'sanitized' => \$status === 'completed'");
requireText('customer/notifications.php', "notifications");
requireText('vendor/notifications.php', "vendor_notifications");
requireText('customer/map.php', "latitude");
requireText('customer/map.php', "value=\"1\">1 km");
requireText('customer/map.php', "value=\"3\">3 km");
requireText('customer/map.php', "value=\"5\" selected>5 km");
requireText('customer/map.php', "value=\"10\">10 km");
requireText('customer/map.php', "function distanceKm");
requireText('customer/map.php', "const inside=km<=radius;");
requireText('vendor/dashboard.php', "save_location");
requireText('customer/stylist.php', "mode");
requireText('customer/tryon.php', "tryon_requests");
requireText('vendor/condition_ai.php', "status='processing'");
requireText('admin/verifications.php', "admin_actions");
requireText('admin/verifications.php', "o2oNotifyVendor");
requireText('admin/moderation.php', "admin_actions");
requireText('admin/moderation.php', "o2oNotifyCustomer");
requireText('admin/audit.php', "FROM admin_actions");
requireText('customer/store.php', "CUSTOMER RATINGS");
requireText('customer/store.php', "pickup_instructions");
requireText('customer/store.php', "delivery_available");
requireText('vendor/dashboard.php', "save_store_profile");


$schema = requireFile('database/database.sql');
foreach ([
    'reward_credits',
    'reward_credit_used',
    'vendor_notifications',
    'latitude DECIMAL(10,7)',
    'longitude DECIMAL(10,7)',
    'product_size_id INT NULL',
    'payment_transactions',
    "payment_status ENUM('pending','paid','failed','refunded')",
    "payment_method ENUM('Cash on Delivery','Online Payment')",
    'rental_tracking_events',
    'opening_time TIME',
    'closing_time TIME',
    'pickup_instructions VARCHAR(500)',
    'delivery_available TINYINT(1)'
] as $needle) {
    if ($schema !== '' && strpos($schema, $needle) === false) {
        $failures[] = "Base schema is missing integration field/table: {$needle}";
    }
}

if ($failures) {
    fwrite(STDERR, "Integration smoke test failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Marketplace integration smoke test passed.\n";

requireText('vendor/login.php', "failed_login_count");
requireText('vendor/login.php', "locked_until");
requireText('admin/login.php', "failed_login_count");
requireText('admin/login.php', "locked_until");
requireText('database/migrations/022_auth_throttling.sql', "vendors");
requireText('database/migrations/022_auth_throttling.sql', "admins");

requireText('shared/payment_lifecycle.php', "status='failed'");
requireText('shared/payment_lifecycle.php', "Payment attempt expired after 30 minutes");
requireText('customer/payment.php', "o2oExpirePendingPayments");
requireText('customer/payment_webhook.php', "if(\$tx['status']==='created')");
requireText('cron/expire_pending_payments.php', "o2oExpirePendingPayments");

requireText('vendor/size_charts.php', "product_sizes");
requireText('vendor/inventory.php', "size_charts.php");
requireText('customer/fit.php', "Combined measurement difference");
