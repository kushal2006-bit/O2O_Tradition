<?php
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax'
]);
session_start();
require_once '../shared/config.php';

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }


if(isset($_SESSION['admin_id'])){header('Location: dashboard.php');exit;}

$db=getDB();$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { $error='Invalid form session. Please refresh and try again.'; }
    else {
    $email=trim($_POST['email']??'');
    $password=$_POST['password']??'';

    if($email===''||$password===''){
        $error='Enter your email and password.';
    }else{
        $st=$db->prepare("SELECT id,name,password FROM admins WHERE email=? LIMIT 1");
        $st->execute([$email]);
        $admin=$st->fetch();

        if($admin && !empty($admin['locked_until']) && strtotime($admin['locked_until']) > time()){$error='Too many failed attempts. Please try again later.';}elseif($admin&&password_verify($password,$admin['password'])){
            session_regenerate_id(true);
            $_SESSION['csrf_token']=bin2hex(random_bytes(32));
            $_SESSION['admin_id']=(int)$admin['id'];
            $_SESSION['admin_name']=$admin['name'];
            $db->prepare('UPDATE admins SET failed_login_count=0,locked_until=NULL WHERE id=?')->execute([(int)$admin['id']]);
            header('Location: dashboard.php');
            exit;
        }
        $error='Invalid admin credentials.';
        if($admin){$failed=(int)$admin['failed_login_count']+1;$locked=$failed>=5?date('Y-m-d H:i:s',time()+900):null;$db->prepare('UPDATE admins SET failed_login_count=?,locked_until=? WHERE id=?')->execute([$failed,$locked,(int)$admin['id']]);}
    }
    }
}
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login – O2O Tradition</title>
<style>body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.wrap{max-width:420px;margin:80px auto;padding:20px}.card{background:#fff;border:1px solid #E8E0D0;padding:28px}.input{width:100%;box-sizing:border-box;padding:12px;margin:7px 0 16px;border:1px solid #ddd}.btn{width:100%;padding:12px;background:#1A1108;color:#C9A84C;border:0}.err{background:#FEF2F2;color:#991B1B;padding:12px;margin-bottom:15px}</style></head>
<body><div class="wrap"><div class="card"><h1>O2O Tradition</h1><h2>Admin Login</h2><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><label>Email</label><input class="input" type="email" name="email" required><label>Password</label><input class="input" type="password" name="password" required><button class="btn">Sign in</button></form></div></div></body></html>