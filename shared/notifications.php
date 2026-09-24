<?php
function o2oNotifyCustomer(PDO $db, int $customerId, string $type, string $title, string $message): void
{
    if ($customerId <= 0) {
        return;
    }
    $db->prepare("INSERT INTO notifications (customer_id, type, title, message) VALUES (?, ?, ?, ?)")
        ->execute([$customerId, $type, $title, $message]);
}

function o2oNotifyVendor(PDO $db, int $vendorId, string $type, string $title, string $message): void
{
    if ($vendorId <= 0) {
        return;
    }
    $db->prepare("INSERT INTO vendor_notifications (vendor_id, type, title, message) VALUES (?, ?, ?, ?)")
        ->execute([$vendorId, $type, $title, $message]);
}
