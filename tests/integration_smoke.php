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
requireText('customer/payment_webhook.php', "retry remains available");
requireText('cron/expire_pending_payments.php', "o2oExpirePendingPayments");

requireText('vendor/size_charts.php', "product_sizes");
requireText('vendor/inventory.php', "size_charts.php");
requireText('customer/fit.php', "Combined measurement difference");

requireText('vendor/dashboard.php', "payment_method='Cash on Delivery' OR payment_status='paid'");
requireText('vendor/dashboard.php', "status='failed',failure_reason='Purchase order cancelled before payment confirmation.'");

requireText('vendor/trust_records.php', "after-return report can only be recorded after the rental is returned");
requireText('vendor/trust_records.php', "Completed sanitization requires a returned and inspected rental");
requireText('customer/item.php', "itemAverageRating");

requireText('customer/login.php', "resend_verification");
requireText('customer/login.php', "at least 60 seconds");
requireText('shared/email.php', "o2oCreateVerificationToken");
requireText('database/migrations/023_verification_resend.sql', "verification_last_sent_at");
requireText('customer/login.php', "forgot_password");
requireText('customer/reset_password.php', "password_reset_token_hash");
requireText('shared/email.php', "o2oCreatePasswordResetToken");
requireText('shared/email.php', "o2oSendPasswordResetEmail");
requireText('database/migrations/024_password_reset.sql', "password_reset_expires_at");
requireText('database/migrations/025_complaints_disputes.sql', "CREATE TABLE IF NOT EXISTS complaints");
requireText('customer/complaints.php', "Submit Complaint");
requireText('admin/complaints.php', "complaint_");
requireText('admin/dashboard.php', "stats['complaints']");
requireText('database/migrations/026_customer_account_controls.sql', "account_status");
requireText('customer/account.php', "Deactivate My Account");
requireText('customer/account.php', "email_notifications_enabled");
requireText('customer/login.php', "account_status");
requireText('vendor/customers.php', "Customers");
requireText('vendor/reviews.php', "Ratings & Reviews");
requireText('vendor/dashboard.php', 'customers.php');
requireText('database/migrations/027_wishlist_alerts.sql', "price_alert");
requireText('database/migrations/028_wishlist_alert_state.sql', "last_notified_available");
requireText('customer/wishlist.php', "Availability alert");
requireText('cron/wishlist_alerts.php', "price_drop");
requireText('database/migrations/029_admin_analytics.sql', 'admin_daily_metrics');
requireText('admin/analytics.php', 'Completed rental and delivered purchase activity');
requireText('database/migrations/030_saved_complete_looks.sql', 'saved_complete_looks');
requireText('customer/complete_look.php', 'Save This Complete Look');
requireText('customer/saved_looks.php', 'Saved Complete Looks');
requireText('customer/privacy.php', 'Download your account data');
requireText('customer/account.php', 'privacy.php');
requireText('shared/payments.php', 'o2oRefundRazorpayPayment');
requireText('shared/notifications.php', 'email_notifications_enabled');
requireText('customer/account.php', 'Allow optional email notifications');
requireText('admin/refunds.php', 'Full Refund');
requireText('admin/dashboard.php', 'refunds.php');
