<?php
const O2O_REWARD_REDEEM_POINTS = 100;
const O2O_REWARD_CREDIT_VALUE = 50.00;

function o2oAwardReward(PDO $db, int $customerId, int $points, string $reason, ?string $sourceType = null, ?int $sourceId = null): bool
{
    if ($customerId <= 0 || $points <= 0) return false;
    $stmt = $db->prepare("INSERT IGNORE INTO rewards (customer_id, points, reason, transaction_type, source_type, source_id) VALUES (?, ?, ?, 'earn', ?, ?)");
    $stmt->execute([$customerId, $points, $reason, $sourceType, $sourceId]);
    return $stmt->rowCount() === 1;
}
function o2oRewardBalance(PDO $db, int $customerId): int
{
    $stmt=$db->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type='earn' THEN points WHEN transaction_type='redeem' THEN -points ELSE points END),0) FROM rewards WHERE customer_id=?");
    $stmt->execute([$customerId]); return max(0,(int)$stmt->fetchColumn());
}
function o2oRewardCreditBalance(PDO $db, int $customerId): float
{
    $stmt=$db->prepare("SELECT COALESCE(SUM(balance),0) FROM reward_credits WHERE customer_id=?");
    $stmt->execute([$customerId]); return round((float)$stmt->fetchColumn(),2);
}
function o2oRedeemRewardPoints(PDO $db, int $customerId, int $points=O2O_REWARD_REDEEM_POINTS): array
{
    if($points!==O2O_REWARD_REDEEM_POINTS) throw new InvalidArgumentException('Only the standard reward redemption amount is available.');
    $started=false; if(!$db->inTransaction()){ $db->beginTransaction(); $started=true; }
    try{
        $stmt=$db->prepare("SELECT transaction_type,points FROM rewards WHERE customer_id=? ORDER BY id FOR UPDATE");$stmt->execute([$customerId]);
        $balance=0; foreach($stmt->fetchAll() as $row){$value=(int)$row['points'];$balance += $row['transaction_type']==='redeem' ? -$value : $value;}
        if($balance<$points) throw new RuntimeException('You do not have enough points to redeem this reward.');
        $reward=$db->prepare("INSERT INTO rewards (customer_id,points,reason,transaction_type) VALUES (?,?,?,'redeem')");
        $reward->execute([$customerId,$points,'Redeemed 100 points for ₹50 marketplace credit']);$rewardId=(int)$db->lastInsertId();
        $credit=$db->prepare("INSERT INTO reward_credits (customer_id,reward_id,credit_amount,balance) VALUES (?,?,?,?)");
        $credit->execute([$customerId,$rewardId,O2O_REWARD_CREDIT_VALUE,O2O_REWARD_CREDIT_VALUE]);
        if($started)$db->commit();
        return ['points'=>$points,'credit'=>O2O_REWARD_CREDIT_VALUE,'remaining_points'=>$balance-$points];
    }catch(Throwable $e){if($started&&$db->inTransaction())$db->rollBack();throw $e;}
}
function o2oRefundRewardCredits(PDO $db, int $customerId, float $amount, int $sourceId): void
{
    if ($customerId <= 0 || $amount <= 0 || $sourceId <= 0) return;
    $check=$db->prepare("SELECT id FROM rewards WHERE customer_id=? AND source_type='payment_refund' AND source_id=? LIMIT 1");
    $check->execute([$customerId,$sourceId]);
    if ($check->fetch()) return;
    $reward=$db->prepare("INSERT INTO rewards (customer_id,points,reason,transaction_type,source_type,source_id) VALUES (?,0,?,'adjustment','payment_refund',?)");
    $reward->execute([$customerId,'Restored reward credit after failed payment',$sourceId]);
    $rewardId=(int)$db->lastInsertId();
    $credit=$db->prepare("INSERT INTO reward_credits (customer_id,reward_id,credit_amount,balance) VALUES (?,?,?,?)");
    $credit->execute([$customerId,$rewardId,round($amount,2),round($amount,2)]);
}

function o2oConsumeRewardCredits(PDO $db, int $customerId, float $amount): float
{
    if($customerId<=0||$amount<=0)return 0.0;
    $remaining=round($amount,2);$applied=0.0;
    $stmt=$db->prepare("SELECT id,balance FROM reward_credits WHERE customer_id=? AND balance>0 ORDER BY id FOR UPDATE");$stmt->execute([$customerId]);
    foreach($stmt->fetchAll() as $credit){
        if($remaining<=0)break;
        $balance=round((float)$credit['balance'],2);$use=min($balance,$remaining);
        $update=$db->prepare("UPDATE reward_credits SET balance=? WHERE id=? AND balance=?");
        $update->execute([round($balance-$use,2),(int)$credit['id'],$balance]);
        if($update->rowCount()===1){$applied+=$use;$remaining-=$use;}
    }
    return round($applied,2);
}
