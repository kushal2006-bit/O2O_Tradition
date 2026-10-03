<?php
function o2oRentalRecordEvent(PDO $db, int $orderId, int $vendorId, string $eventType, string $notes=''): bool
{
    $allowed = ['confirmed','picked_up','returned','inspected','sanitized','cancelled'];
    if ($orderId <= 0 || $vendorId <= 0 || !in_array($eventType, $allowed, true)) return false;

    $orderStmt = $db->prepare("SELECT id,status FROM orders WHERE id=? AND vendor_id=? LIMIT 1");
    $orderStmt->execute([$orderId, $vendorId]);
    $order = $orderStmt->fetch();
    if (!$order) return false;

    $status = (string)$order['status'];
    $validTransition = match ($eventType) {
        'confirmed' => $status === 'new' && !o2oRentalHasEvent($db, $orderId, 'confirmed'),
        'picked_up' => $status === 'in_progress'
            && o2oRentalHasEvent($db, $orderId, 'confirmed')
            && !o2oRentalHasEvent($db, $orderId, 'picked_up'),
        'returned' => $status === 'completed'
            && o2oRentalHasEvent($db, $orderId, 'picked_up')
            && !o2oRentalHasEvent($db, $orderId, 'returned'),
        'inspected' => $status === 'completed'
            && o2oRentalHasEvent($db, $orderId, 'returned')
            && !o2oRentalHasEvent($db, $orderId, 'inspected'),
        'sanitized' => $status === 'completed'
            && o2oRentalHasEvent($db, $orderId, 'returned')
            && o2oRentalHasEvent($db, $orderId, 'inspected')
            && !o2oRentalHasEvent($db, $orderId, 'sanitized'),
        'cancelled' => $status === 'cancelled' && !o2oRentalHasEvent($db, $orderId, 'cancelled'),
        default => false,
    };
    if (!$validTransition) return false;

    $stmt = $db->prepare("INSERT IGNORE INTO rental_tracking_events (rental_order_id, vendor_id, event_type, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$orderId, $vendorId, $eventType, $notes !== '' ? $notes : null]);
    return $stmt->rowCount() === 1;
}

function o2oRentalHasEvent(PDO $db, int $orderId, string $eventType): bool
{
    if ($orderId <= 0 || !in_array($eventType, ['confirmed','picked_up','returned','inspected','sanitized','cancelled'], true)) return false;
    $stmt = $db->prepare("SELECT 1 FROM rental_tracking_events WHERE rental_order_id=? AND event_type=? LIMIT 1");
    $stmt->execute([$orderId, $eventType]);
    return (bool)$stmt->fetchColumn();
}
?>