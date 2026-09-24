<?php
// Shared request-security helpers. Include after session_start().
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function o2oCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function o2oRequireCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Invalid form session. Please refresh and try again.');
    }
}
?>