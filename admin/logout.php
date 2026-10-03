<?php
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax'
]);
session_start();
require_once '../shared/security.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    o2oRequireCsrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$params['path'],'domain'=>$params['domain'],'secure'=>$params['secure'],'httponly'=>$params['httponly'],'samesite'=>'Lax']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

$csrf = o2oCsrfToken();
?><!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign out</title></head>
<body style="font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;display:flex;justify-content:center;padding:70px 20px">
<main style="background:#fff;padding:30px;max-width:420px;width:100%;box-shadow:0 2px 12px #0001">
<h1>Sign out?</h1>
<p>Your session will be ended on this device.</p>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
<button type="submit" style="padding:11px 18px;background:#1A1108;color:#C9A84C;border:0;cursor:pointer">Sign out</button>
</form>
</main>
</body>
</html>