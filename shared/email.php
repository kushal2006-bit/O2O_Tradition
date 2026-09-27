<?php
/**
 * Transactional email helpers.
 *
 * Brevo is used when BREVO_API_KEY is configured. The key can be supplied
 * through the environment or through a private PHP config file outside the
 * public web root:
 *
 *   /home/o2otra0675/secure/email_config.php
 *
 * The private file may define:
 *   $O2O_EMAIL_CONFIG = [
 *       'brevo_api_key' => '...',
 *       'mail_from' => 'verified-sender@example.com',
 *       'app_url' => 'https://example.com/o2o-staging',
 *   ];
 *
 * Never commit real credentials to GitHub.
 */

function o2oEmailConfig(): array {
    static $config = null;
    if ($config !== null) return $config;

    $config = [];

    $privateFile = trim((string)(getenv('O2O_EMAIL_CONFIG_FILE') ?: ''));
    if ($privateFile === '') {
        $privateFile = '/home/o2otra0675/secure/email_config.php';
    }

    if (is_file($privateFile) && is_readable($privateFile)) {
        $O2O_EMAIL_CONFIG = [];
        try {
            require $privateFile;
            if (is_array($O2O_EMAIL_CONFIG)) {
                $config = $O2O_EMAIL_CONFIG;
            }
        } catch (Throwable $e) {
            error_log('O2O email private config could not be loaded: ' . $e->getMessage());
        }
    }

    return $config;
}

function o2oEmailSetting(string $key): string {
    $envNames = [
        'brevo_api_key' => 'BREVO_API_KEY',
        'mail_from' => 'O2O_MAIL_FROM',
        'app_url' => 'O2O_APP_URL',
    ];

    $envName = $envNames[$key] ?? '';
    if ($envName !== '') {
        $value = trim((string)(getenv($envName) ?: ''));
        if ($value !== '') return $value;
    }

    $config = o2oEmailConfig();
    return trim((string)($config[$key] ?? ''));
}

function o2oMailFrom(): string {
    $from = o2oEmailSetting('mail_from');
    if ($from === '' || preg_match('/[\r\n]/', $from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return $from;
}

function o2oAppUrl(): string {
    $base = rtrim(o2oEmailSetting('app_url'), '/');
    if ($base === '' || preg_match('/[\r\n]/', $base) || !preg_match('#^https://#i', $base)) {
        return '';
    }
    return $base;
}

function o2oSendTransactionalEmail(string $to, string $name, string $subject, string $body): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $apiKey = o2oEmailSetting('brevo_api_key');
    $from = o2oMailFrom();

    if ($apiKey === '' || $from === '' || !function_exists('curl_init')) {
        return false;
    }

    $payload = [
        'sender' => [
            'name' => 'O2O Tradition',
            'email' => $from,
        ],
        'to' => [[
            'email' => $to,
            'name' => $name,
        ]],
        'subject' => $subject,
        'textContent' => $body,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) return false;

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log('O2O Brevo email request failed: ' . $curlError);
        return false;
    }

    if ($status < 200 || $status >= 300) {
        error_log('O2O Brevo email request returned HTTP ' . $status . ': ' . substr((string)$response, 0, 1000));
        return false;
    }

    return true;
}

function o2oCreateVerificationToken(PDO $db, int $customerId): ?string {
    if ($customerId <= 0) return null;
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("UPDATE customers SET verification_token_hash=?,verification_expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE),verification_last_sent_at=NOW() WHERE id=? AND email_verified_at IS NULL");
    $stmt->execute([$hash, $customerId]);
    return $stmt->rowCount() === 1 ? $token : null;
}

function o2oCreatePasswordResetToken(PDO $db, int $customerId): ?string {
    if ($customerId <= 0) return null;
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("UPDATE customers SET password_reset_token_hash=?,password_reset_expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE),password_reset_last_sent_at=NOW() WHERE id=?");
    $stmt->execute([$hash, $customerId]);
    return $stmt->rowCount() === 1 ? $token : null;
}

function o2oSendVerificationEmail(string $to, string $name, string $token): bool {
    $base = o2oAppUrl();
    if ($base === '') return false;

    $url = $base . '/customer/verify_email.php?token=' . rawurlencode($token);
    $subject = 'Verify your O2O Tradition account';
    $body = "Hello " . $name . ",\n\nVerify your O2O Tradition email address using this link:\n" . $url . "\n\nThis link expires in 30 minutes.\n\nIf you did not create this account, ignore this email.";

    return o2oSendTransactionalEmail($to, $name, $subject, $body);
}

function o2oSendPasswordResetEmail(string $to, string $name, string $token): bool {
    $base = o2oAppUrl();
    if ($base === '') return false;

    $url = $base . '/customer/reset_password.php?token=' . rawurlencode($token);
    $subject = 'Reset your O2O Tradition password';
    $body = "Hello " . $name . ",\n\nReset your O2O Tradition password using this link:\n" . $url . "\n\nThis link expires in 30 minutes.\n\nIf you did not request a password reset, ignore this email.";

    return o2oSendTransactionalEmail($to, $name, $subject, $body);
}

function o2oCreateLoginOtp(PDO $db, int $customerId): ?string {
    if ($customerId <= 0) return null;
    $otp = (string)random_int(100000, 999999);
    $hash = hash('sha256', $otp);
    $st = $db->prepare("UPDATE customers SET otp_token_hash=?,otp_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),otp_last_sent_at=NOW(),otp_failed_attempts=0 WHERE id=? AND account_status='active' AND email_verified_at IS NOT NULL");
    $st->execute([$hash, $customerId]);
    return $st->rowCount() === 1 ? $otp : null;
}

function o2oSendLoginOtpEmail(string $to, string $name, string $otp): bool {
    $subject = 'Your O2O Tradition sign-in code';
    $body = "Hello " . $name . ",\n\nYour O2O Tradition sign-in code is: " . $otp . "\n\nThis code expires in 10 minutes. If you did not request it, you can ignore this email.";

    return o2oSendTransactionalEmail($to, $name, $subject, $body);
}
?>
