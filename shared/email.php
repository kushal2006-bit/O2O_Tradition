<?php
function o2oMailFrom(): string {
    $from=trim((string)(getenv('O2O_MAIL_FROM')?:''));
    if($from===''||preg_match('/[\r\n]/',$from)||!filter_var($from,FILTER_VALIDATE_EMAIL)) return '';
    return $from;
}
function o2oAppUrl(): string {
    $base=rtrim((string)(getenv('O2O_APP_URL')?:''),'/');
    if($base===''||preg_match('/[\r\n]/',$base)||!preg_match('#^https://#i',$base)) return '';
    return $base;
}
function o2oSendVerificationEmail(string $to,string $name,string $token): bool {
    $from=o2oMailFrom();
    if($from==='') return false;
    $base=o2oAppUrl();
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

function o2oCreatePasswordResetToken(PDO $db, int $customerId): ?string {
    if ($customerId <= 0) return null;
    $token=bin2hex(random_bytes(32));
    $hash=hash('sha256',$token);
    $stmt=$db->prepare("UPDATE customers SET password_reset_token_hash=?,password_reset_expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE),password_reset_last_sent_at=NOW() WHERE id=?");
    $stmt->execute([$hash,$customerId]);
    return $stmt->rowCount()===1?$token:null;
}

function o2oSendPasswordResetEmail(string $to,string $name,string $token): bool {
    $from=o2oMailFrom();
    $base=o2oAppUrl();
    if($from===''||$base==='') return false;
    $url=$base.'/customer/reset_password.php?token='.rawurlencode($token);
    $subject='Reset your O2O Tradition password';
    $body="Hello ".$name.",\n\nReset your O2O Tradition password using this link:\n".$url."\n\nThis link expires in 30 minutes.\n\nIf you did not request a password reset, ignore this email.";
    $headers="From: ".$from."\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to,$subject,$body,$headers);
}
function o2oCreateLoginOtp(PDO $db,int $customerId): ?string {
    if($customerId<=0)return null;
    $otp=(string)random_int(100000,999999);
    $hash=hash('sha256',$otp);
    $st=$db->prepare("UPDATE customers SET otp_token_hash=?,otp_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),otp_last_sent_at=NOW(),otp_failed_attempts=0 WHERE id=? AND account_status='active' AND email_verified_at IS NOT NULL");
    $st->execute([$hash,$customerId]);
    return $st->rowCount()===1?$otp:null;
}
function o2oSendLoginOtpEmail(string $to,string $name,string $otp): bool {
    $from=o2oMailFrom();
    if($from==='')return false;
    $subject='Your O2O Tradition sign-in code';
    $body="Hello ".$name.",\n\nYour O2O Tradition sign-in code is: ".$otp."\n\nThis code expires in 10 minutes. If you did not request it, you can ignore this email.";
    return @mail($to,$subject,$body,"From: ".$from."\r\nContent-Type: text/plain; charset=UTF-8\r\n");
}
?>