<?php
session_start();
require_once '../shared/config.php';

if(isset($_SESSION['admin_id'])){header('Location: dashboard.php');exit;}

$db=getDB();$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim($_POST['email']??'');
    $password=$_POST['password']??'';

    if($email===''||$password===''){
        $error='Enter your email and password.';
    }else{
        $st=$db->prepare("SELECT id,name,password FROM admins WHERE email=? LIMIT 1");
        $st->execute([$email]);
        $admin=$st->fetch();

        if($admin&&password_verify($password,$admin['password'])){
            session_regenerate_id(true);
            $_SESSION['admin_id']=(int)$admin['id'];
            $_SESSION['admin_name']=$admin['name'];
            header('Location: dashboard.php');
            exit;
        }
        $error='Invalid admin credentials.';
    }
}
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login – O2O Tradition</title>
<style>body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.wrap{max-width:420px;margin:80px auto;padding:20px}.card{background:#fff;border:1px solid #E8E0D0;padding:28px}.input{width:100%;box-sizing:border-box;padding:12px;margin:7px 0 16px;border:1px solid #ddd}.btn{width:100%;padding:12px;background:#1A1108;color:#C9A84C;border:0}.err{background:#FEF2F2;color:#991B1B;padding:12px;margin-bottom:15px}</style></head>
<body><div class="wrap"><div class="card"><h1>O2O Tradition</h1><h2>Admin Login</h2><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><form method="POST"><label>Email</label><input class="input" type="email" name="email" required><label>Password</label><input class="input" type="password" name="password" required><button class="btn">Sign in</button></form></div></div></body></html>