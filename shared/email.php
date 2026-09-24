<?php
function o2oSendVerificationEmail(string $to,string $name,string $token): bool {
    $from=trim((string)(getenv('O2O_MAIL_FROM')?:''));
    if($from==='') return false;
    $base=rtrim((string)(getenv('O2O_APP_URL')?:''),'/');
    if($base==='') return false;
    $url=$base.'/customer/verify_email.php?token='.rawurlencode($token);
    $subject='Verify your O2O Tradition account';
    $body="Hello ".$name.",\n\nVerify your O2O Tradition email address using this link:\n".$url."\n\nThis link expires in 30 minutes.\n\nIf you did not create this account, ignore this email.";
    $headers="From: ".$from."\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to,$subject,$body,$headers);
}

function o2oCreateVerificationToken(PDO $db, int $customerId): ?string {
    if ($customerId <= 0) return null;
    $token=bin2hex(random_bytes(32));
    $hash=hash('sha256',$token);
    $stmt=$db->prepare("UPDATE customers SET verification_token_hash=?,verification_expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE),verification_last_sent_at=NOW() WHERE id=? AND email_verified_at IS NULL");
    $stmt->execute([$hash,$customerId]);
    return $stmt->rowCount()===1?$token:null;
}
?>