<?php
function o2oRentalRecordEvent(PDO $db, int $orderId, int $vendorId, string $eventType, string $notes=''): bool
{
    if ($orderId <= 0 || $vendorId <= 0 || !in_array($eventType, ['confirmed','picked_up','returned','inspected','sanitized','cancelled'], true)) {
        return false;
    }
    $stmt = $db->prepare("INSERT IGNORE INTO rental_tracking_events (rental_order_id, vendor_id, event_type, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$orderId, $vendorId, $eventType, $notes !== '' ? $notes : null]);
    return $stmt->rowCount() === 1;
}

function o2oRentalHasEvent(PDO $db, int $orderId, string $eventType): bool
{
    if ($orderId <= 0 || !in_array($eventType, ['confirmed','picked_up','returned','inspected','sanitized','cancelled'], true)) {
        return false;
    }
    $stmt = $db->prepare("SELECT 1 FROM rental_tracking_events WHERE rental_order_id=? AND event_type=? LIMIT 1");
    $stmt->execute([$orderId, $eventType]);
    return (bool)$stmt->fetchColumn();
}
?>