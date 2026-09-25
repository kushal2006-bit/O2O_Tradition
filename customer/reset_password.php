<?php
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
o2oCsrfToken();

$db=getDB();
$token=trim($_GET['token']??$_POST['token']??'');
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    o2oRequireCsrf();
    $password=$_POST['password']??'';$confirm=$_POST['confirm_password']??'';
    if(strlen($password)<8){$error='Password must be at least 8 characters.';}
    elseif($password!==$confirm){$error='Passwords do not match.';}
    elseif($token===''){$error='This reset link is invalid or expired.';}
    else{
        $hash=hash('sha256',$token);
        $st=$db->prepare("SELECT id FROM customers WHERE password_reset_token_hash=? AND password_reset_expires_at>NOW() LIMIT 1");
        $st->execute([$hash]);$customer=$st->fetch();
        if(!$customer){$error='This reset link is invalid or expired.';}
        else{
            $newHash=password_hash($password,PASSWORD_DEFAULT);
            $u=$db->prepare("UPDATE customers SET password=?,password_reset_token_hash=NULL,password_reset_expires_at=NULL,password_reset_last_sent_at=NULL,failed_login_count=0,locked_until=NULL WHERE id=? AND password_reset_token_hash=?");
            $u->execute([$newHash,(int)$customer['id'],$hash]);
            if($u->rowCount()===1)$success='Your password has been reset. You can now sign in.';
            else $error='This reset link is no longer active.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password – O2O Tradition</title>
<style>body{font-family:Arial;background:#FAF6EE;color:#3D2B0F}.card{max-width:520px;margin:80px auto;background:#fff;border:1px solid #E8E0D0;padding:30px}.field{margin:16px 0}.field label{display:block;font-size:12px;color:#777;margin-bottom:7px}.field input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ddd}.btn{width:100%;padding:13px;background:#1A1108;color:#C9A84C;border:0;font-weight:bold}.ok{background:#ECFDF5;color:#065F46;padding:12px}.err{background:#FEF2F2;color:#991B1B;padding:12px}</style></head>
<body><main class="card"><h1>O2O Tradition</h1><h2>Reset Password</h2>
<?php if($success):?><div class="ok"><?=htmlspecialchars($success)?></div><p><a href="login.php">Go to Login</a></p>
<?php else:?><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
<div class="field"><label>New password</label><input type="password" name="password" minlength="8" required></div>
<div class="field"><label>Confirm password</label><input type="password" name="confirm_password" minlength="8" required></div>
<button class="btn" type="submit">Reset Password</button></form>
<p style="margin-top:16px"><a href="login.php">Back to Login</a></p><?php endif;?></main></body></html>