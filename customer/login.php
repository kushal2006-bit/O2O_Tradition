<?php
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax'
]);
session_start();
require_once '../shared/config.php';
require_once '../shared/email.php';

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }


if (isLoggedIn('customer')) {
    header('Location: home.php');
    exit;
}

$error = '';
$success = '';
$resendMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { $error = 'Invalid form session. Please refresh and try again.'; }
    else {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'request_otp') {
        $email=trim($_POST['otp_email']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$error='Enter a valid email address.';}else{$db=getDB();$st=$db->prepare("SELECT id,name,email,email_verified_at,otp_last_sent_at FROM customers WHERE email=? LIMIT 1");$st->execute([$email]);$customer=$st->fetch();if($customer&&$customer['email_verified_at']&&(!$customer['otp_last_sent_at']||strtotime($customer['otp_last_sent_at'])<=time()-60)){$otp=o2oCreateLoginOtp($db,(int)$customer['id']);if($otp&&o2oSendLoginOtpEmail($customer['email'],$customer['name'],$otp))$resendMessage='If that account is eligible, a sign-in code has been sent. It expires in 10 minutes.';else$error='The sign-in code could not be sent. Check production mail settings.';}else{$resendMessage='If that account is eligible, a sign-in code has been sent.';}}
    } elseif ($action === 'otp_login') {
        $email=trim($_POST['otp_email']??'');
        $otp=trim($_POST['otp']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^[0-9]{6}$/',$otp)){
            $error='Enter your email and the 6-digit code.';
        } else {
            $db=getDB();
            $st=$db->prepare("SELECT * FROM customers WHERE email=? LIMIT 1");
            $st->execute([$email]);
            $customer=$st->fetch();
            $valid=false;
            if($customer){
                $valid=($customer['account_status']??'active')==='active'
                    && !empty($customer['email_verified_at'])
                    && !empty($customer['otp_token_hash'])
                    && !empty($customer['otp_expires_at'])
                    && strtotime($customer['otp_expires_at'])>time()
                    && (int)$customer['otp_failed_attempts']<5;
            }
            if($valid && hash_equals($customer['otp_token_hash'],hash('sha256',$otp))){
                session_regenerate_id(true);
                $_SESSION['csrf_token']=bin2hex(random_bytes(32));
                $_SESSION['customer_id']=$customer['id'];
                $_SESSION['customer_name']=$customer['name'];
                $_SESSION['customer_pincode']=$customer['pincode'];
                $clear=$db->prepare("UPDATE customers SET otp_token_hash=NULL,otp_expires_at=NULL,otp_failed_attempts=0 WHERE id=?");
                $clear->execute([(int)$customer['id']]);
                header('Location: home.php');
                exit;
            }
            if($customer){
                $failed=$db->prepare("UPDATE customers SET otp_failed_attempts=LEAST(otp_failed_attempts+1,5) WHERE id=?");
                $failed->execute([(int)$customer['id']]);
            }
            $error='Invalid or expired sign-in code.';
        }

    } elseif ($action === 'forgot_password') {
        $email=trim($_POST['forgot_email']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ $error='Enter a valid email address.'; }
        else{
            $db=getDB();
            $st=$db->prepare("SELECT id,name,email,password_reset_last_sent_at FROM customers WHERE email=? LIMIT 1");$st->execute([$email]);$customer=$st->fetch();
            if($customer && (!empty($customer['password_reset_last_sent_at']) && strtotime($customer['password_reset_last_sent_at'])>time()-60)){
                $resendMessage='If that account exists, a password reset email will be sent.';
            }elseif($customer){
                $token=o2oCreatePasswordResetToken($db,(int)$customer['id']);
                if($token && o2oSendPasswordResetEmail($customer['email'],$customer['name'],$token)) $resendMessage='If that account exists, a password reset email has been sent. The link expires in 30 minutes.';
                else $error='The password reset email could not be sent. Check production mail settings.';
            }else{
                $resendMessage='If that account exists, a password reset email has been sent.';
            }
        }
    } elseif ($action === 'resend_verification') {
        $email=trim($_POST['resend_email']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ $error='Enter a valid email address.'; }
        else{
            $db=getDB();
            $st=$db->prepare("SELECT id,name,email,email_verified_at,verification_last_sent_at FROM customers WHERE email=? LIMIT 1");$st->execute([$email]);$customer=$st->fetch();
            if(!$customer || !empty($customer['email_verified_at'])){ $resendMessage='If that account needs verification, a new email will be sent.'; }
            elseif(!empty($customer['verification_last_sent_at']) && strtotime($customer['verification_last_sent_at'])>time()-60){ $error='Please wait at least 60 seconds before requesting another verification email.'; }
            else{
                $token=o2oCreateVerificationToken($db,(int)$customer['id']);
                if($token && o2oSendVerificationEmail($customer['email'],$customer['name'],$token)) $resendMessage='A new verification email has been sent. The link expires in 30 minutes.';
                else $error='The verification email could not be sent. Check production mail settings.';
            }
        }
    } elseif ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            if ($customer && password_verify($password, $customer['password'])) {
                if (($customer['account_status'] ?? 'active') !== 'active') { $error = 'This account is deactivated. Contact support to restore access.'; }
                elseif (!empty($customer['locked_until']) && strtotime($customer['locked_until']) > time()) { $error = 'Too many failed attempts. Please try again later.'; }
                elseif (empty($customer['email_verified_at'])) { $error = 'Please verify your email address before signing in.'; }
                else {
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_pincode'] = $customer['pincode'];
                header('Location: home.php');
                exit;
                }
            } else {
                if ($customer) {
                    $failed=(int)$customer['failed_login_count']+1;$locked=$failed>=5?date('Y-m-d H:i:s',time()+900):null;
                    $db->prepare('UPDATE customers SET failed_login_count=?,locked_until=? WHERE id=?')->execute([$failed,$locked,(int)$customer['id']]);
                }
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    } elseif ($action === 'register') {
        $name     = trim($_POST['reg_name'] ?? '');
        $email    = trim($_POST['reg_email'] ?? '');
        $phone    = trim($_POST['reg_phone'] ?? '');
        $pincode  = trim($_POST['reg_pincode'] ?? '');
        $address  = trim($_POST['reg_address'] ?? '');
        $password = $_POST['reg_password'] ?? '';

        if ($name && $email && $password && $pincode) {
            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $db = getDB();
                $check = $db->prepare("SELECT id FROM customers WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = 'Email already registered.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $stmt = $db->prepare("INSERT INTO customers (name, email, phone, pincode, address, password, verification_token_hash, verification_expires_at) VALUES (?,?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
                    $stmt->execute([$name, $email, $phone, $pincode, $address, $hash, $tokenHash]);
                    if ($db->prepare('UPDATE customers SET verification_last_sent_at=NOW() WHERE id=?')->execute([(int)$db->lastInsertId()]) && o2oSendVerificationEmail($email, $name, $token)) {
                        $success = 'Registration complete. Check your email to verify your account before signing in.';
                    } else {
                        $error = 'Registration saved, but the verification email could not be sent. Production mail settings are not configured yet.';
                    }
                }
            }
        } else {
            $error = 'Please fill all required fields.';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vasanam – Traditional Attire Rental</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --gold: #C9A84C;
    --gold-light: #E8CC82;
    --crimson: #8B1A1A;
    --cream: #FAF6EE;
    --dark: #1A1108;
    --text: #3D2B0F;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Jost', sans-serif;
    background: var(--cream);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-image:
      radial-gradient(ellipse 80% 60% at 20% 50%, rgba(201,168,76,0.08) 0%, transparent 70%),
      radial-gradient(ellipse 60% 80% at 80% 50%, rgba(139,26,26,0.06) 0%, transparent 70%);
  }
  .container {
    width: 100%;
    max-width: 900px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 580px;
    box-shadow: 0 30px 80px rgba(26,17,8,0.18);
    border-radius: 4px;
    overflow: hidden;
  }
  .brand-panel {
    background: var(--dark);
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }
  .brand-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
      repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(201,168,76,0.03) 20px, rgba(201,168,76,0.03) 21px);
  }
  .brand-panel::after {
    content: '';
    position: absolute;
    top: -100px; right: -100px;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(201,168,76,0.1), transparent 70%);
  }
  .logo {
    font-family: 'Cormorant Garamond', serif;
    font-size: 48px;
    font-weight: 300;
    color: var(--gold);
    letter-spacing: 4px;
    position: relative;
    z-index: 1;
  }
  .logo-sub {
    font-family: 'Jost', sans-serif;
    font-size: 11px;
    letter-spacing: 5px;
    color: rgba(201,168,76,0.5);
    text-transform: uppercase;
    margin-top: 6px;
    position: relative;
    z-index: 1;
  }
  .brand-divider {
    width: 40px;
    height: 1px;
    background: var(--gold);
    margin: 30px 0;
    position: relative;
    z-index: 1;
  }
  .brand-tagline {
    font-family: 'Cormorant Garamond', serif;
    font-size: 22px;
    font-style: italic;
    color: rgba(250,246,238,0.7);
    line-height: 1.5;
    position: relative;
    z-index: 1;
  }
  .brand-motif {
    margin-top: 40px;
    display: flex;
    gap: 10px;
    position: relative;
    z-index: 1;
  }
  .motif-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--gold);
    opacity: 0.4;
  }
  .motif-dot:nth-child(2) { opacity: 0.7; }
  .motif-dot:nth-child(3) { opacity: 1; }
  .form-panel {
    background: white;
    padding: 50px 45px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .tab-row {
    display: flex;
    gap: 0;
    margin-bottom: 35px;
    border-bottom: 1px solid #E8E0D0;
  }
  .tab-btn {
    flex: 1;
    background: none;
    border: none;
    padding: 12px;
    font-family: 'Jost', sans-serif;
    font-size: 13px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #999;
    cursor: pointer;
    position: relative;
    transition: color 0.3s;
  }
  .tab-btn.active {
    color: var(--text);
  }
  .tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -1px; left: 0; right: 0;
    height: 2px;
    background: var(--gold);
  }
  .form-section { display: none; }
  .form-section.active { display: block; }
  .form-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 28px;
    color: var(--text);
    margin-bottom: 6px;
  }
  .form-subtitle {
    font-size: 13px;
    color: #999;
    margin-bottom: 28px;
  }
  .field { margin-bottom: 18px; }
  .field label {
    display: block;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 7px;
  }
  .field input, .field textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #DDD;
    border-radius: 2px;
    font-family: 'Jost', sans-serif;
    font-size: 14px;
    color: var(--text);
    background: #FDFAF5;
    transition: border-color 0.3s;
    outline: none;
  }
  .field input:focus, .field textarea:focus { border-color: var(--gold); background: white; }
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .btn-primary {
    width: 100%;
    padding: 13px;
    background: var(--dark);
    color: var(--gold);
    border: none;
    border-radius: 2px;
    font-family: 'Jost', sans-serif;
    font-size: 12px;
    letter-spacing: 3px;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.3s, transform 0.1s;
    margin-top: 10px;
  }
  .btn-primary:hover { background: var(--crimson); }
  .btn-primary:active { transform: scale(0.99); }
  .alert {
    padding: 10px 14px;
    border-radius: 2px;
    font-size: 13px;
    margin-bottom: 16px;
  }
  .alert-error { background: #FEF2F2; color: #991B1B; border-left: 3px solid #EF4444; }
  .demo-note {
    margin-top: 20px;
    padding: 12px;
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: 2px;
    font-size: 12px;
    color: #92400E;
  }
  @media (max-width: 700px) {
    .container { grid-template-columns: 1fr; }
    .brand-panel { padding: 40px 30px; }
    .form-panel { padding: 35px 25px; }
  }
</style>
</head>
<body>
<div class="container">
  <div class="brand-panel">
    <div class="logo">Vasanam</div>
    <div class="logo-sub">Traditional Attire Rental</div>
    <div class="brand-divider"></div>
    <div class="brand-tagline">Drape yourself in the richness of culture — for every celebration</div>
    <div class="brand-motif">
      <div class="motif-dot"></div>
      <div class="motif-dot"></div>
      <div class="motif-dot"></div>
    </div>
  </div>
  <div class="form-panel">
    <div style="margin:0 0 20px;padding:12px;background:#FFFBEB;border:1px solid #FDE68A;font-size:12px">
      Forgot your password? Use the reset form below. We never reveal whether an email address is registered.
      <form method="POST" style="margin-top:10px">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="forgot_password">
        <input type="email" name="forgot_email" placeholder="Account email" required style="width:100%;padding:10px;box-sizing:border-box;border:1px solid #DDD">
        <button class="btn-primary" type="submit">Send Password Reset</button>
      </form>
    </div>

    <div class="tab-row">
      <button class="tab-btn active" onclick="switchTab('login', this)">Login</button>
      <button class="tab-btn" onclick="switchTab('register', this)">Sign Up</button>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($resendMessage): ?><div class="alert" style="background:#ECFDF5;color:#065F46;border-left:3px solid #10B981"><?= htmlspecialchars($resendMessage) ?></div><?php endif; ?>

    <div id="tab-login" class="form-section active">
      <div class="form-title">Welcome Back</div>
      <div class="form-subtitle">Sign in to browse and rent attire</div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="login">
        <div class="field">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="you@example.com" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn-primary">Sign In</button>
      </form>
      <div class="demo-note">Passwordless sign-in: request a one-time code by email, then enter the 6-digit code here. Codes expire after 10 minutes.</div>
      <form method="POST" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="request_otp">
        <div class="field"><label>Email Address</label><input type="email" name="otp_email" placeholder="you@example.com" required></div>
        <button type="submit" class="btn-primary">Send Sign-In Code</button>
      </form>
      <form method="POST" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="otp_login">
        <div class="field"><label>Email Address</label><input type="email" name="otp_email" placeholder="you@example.com" required></div>
        <div class="field"><label>6-Digit Code</label><input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></div>
        <button type="submit" class="btn-primary">Sign In With Code</button>
      </form>
      <div class="demo-note">Need a new verification link? Enter your email below.</div>
      <form method="POST" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="resend_verification">
        <div class="field"><label>Email Address</label><input type="email" name="resend_email" placeholder="you@example.com" required></div>
        <button type="submit" class="btn-primary">Resend Verification Email</button>
      </form>
    </div>

    <div id="tab-register" class="form-section">
      <div class="form-title">Create Account</div>
      <div class="form-subtitle">Join Vasanam today</div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="register">
        <div class="field-row">
          <div class="field">
            <label>Full Name *</label>
            <input type="text" name="reg_name" placeholder="Your name" required>
          </div>
          <div class="field">
            <label>Phone</label>
            <input type="tel" name="reg_phone" placeholder="Mobile number">
          </div>
        </div>
        <div class="field">
          <label>Email Address *</label>
          <input type="email" name="reg_email" placeholder="you@example.com" required>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Pincode *</label>
            <input type="text" name="reg_pincode" placeholder="400001" required>
          </div>
          <div class="field">
            <label>Password *</label>
            <input type="password" name="reg_password" placeholder="Min 8 chars" required>
          </div>
        </div>
        <div class="field">
          <label>Address</label>
          <input type="text" name="reg_address" placeholder="Street, City">
        </div>
        <button type="submit" class="btn-primary">Create Account</button>
      </form>
    </div>
  </div>
</div>
<script>
function switchTab(tab, btn) {
  document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  btn.classList.add('active');
}
</script>
</body>
</html>