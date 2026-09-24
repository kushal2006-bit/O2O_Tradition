<?php
require_once __DIR__.'/rewards.php';
function o2oExpirePendingPayments(PDO $db, int $ageMinutes = 30): int
{
    $ageMinutes = max(5, min(1440, $ageMinutes));
    $cutoff = date('Y-m-d H:i:s', time() - ($ageMinutes * 60));
    $stmt = $db->prepare("SELECT id,customer_id,order_type,order_id FROM payment_transactions WHERE provider='razorpay' AND status='created' AND created_at < ? ORDER BY id ASC LIMIT 50");
    $stmt->execute([$cutoff]);
    $expired = 0;

    foreach ($stmt->fetchAll() as $tx) {
        $db->beginTransaction();
        try {
            $lock = $db->prepare("SELECT * FROM payment_transactions WHERE id=? AND status='created' FOR UPDATE");
            $lock->execute([(int)$tx['id']]);
            $locked = $lock->fetch();
            if (!$locked) { $db->rollBack(); continue; }

            $updated = $db->prepare("UPDATE payment_transactions SET status='failed',failure_reason=? WHERE id=? AND status='created'");
            $updated->execute(['Payment attempt expired after 30 minutes without confirmation.', (int)$tx['id']]);
            if ($updated->rowCount() !== 1) { $db->rollBack(); continue; }

            if ($tx['order_type'] === 'rental') {
                $orderStmt = $db->prepare("SELECT reward_credit_used FROM orders WHERE id=? AND customer_id=? FOR UPDATE");
                $orderStmt->execute([(int)$tx['order_id'], (int)$tx['customer_id']]);
                $order = $orderStmt->fetch();
                if ($order) {
                    o2oRefundRewardCredits($db, (int)$tx['customer_id'], (float)$order['reward_credit_used'], (int)$tx['id']);
                    $db->prepare("UPDATE orders SET payment_status='failed',status='cancelled' WHERE id=? AND status='new'")->execute([(int)$tx['order_id']]);
                }
            } else {
                $orderStmt = $db->prepare("SELECT reward_credit_used FROM purchase_orders WHERE id=? AND customer_id=? FOR UPDATE");
                $orderStmt->execute([(int)$tx['order_id'], (int)$tx['customer_id']]);
                $order = $orderStmt->fetch();
                if ($order) {
                    o2oRefundRewardCredits($db, (int)$tx['customer_id'], (float)$order['reward_credit_used'], (int)$tx['id']);
                    $db->prepare("UPDATE purchase_orders SET payment_status='failed',order_status='cancelled' WHERE id=? AND order_status='confirmed'")->execute([(int)$tx['order_id']]);
                    $db->prepare("UPDATE product_modes pm JOIN purchase_order_items poi ON poi.item_id=pm.item_id SET pm.available=1 WHERE poi.order_id=? AND pm.mode='buy'")->execute([(int)$tx['order_id']]);
                    $db->prepare("UPDATE items i JOIN purchase_order_items poi ON poi.item_id=i.id SET i.available=1 WHERE poi.order_id=? AND i.available=0")->execute([(int)$tx['order_id']]);
                }
            }

            $db->commit();
            $expired++;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('O2O payment expiry failed for transaction '.(int)$tx['id'].': '.$e->getMessage());
        }
    }
    return $expired;
}
?>