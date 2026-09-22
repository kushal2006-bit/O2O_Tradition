<?php
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax'
]);
session_start();
require_once '../shared/config.php';

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }


if (isLoggedIn('customer')) {
    header('Location: home.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { $error = 'Invalid form session. Please refresh and try again.'; }
    else {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            if ($customer && password_verify($password, $customer['password'])) {
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_pincode'] = $customer['pincode'];
                header('Location: home.php');
                exit;
            } else {
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
            $db = getDB();
            $check = $db->prepare("SELECT id FROM customers WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO customers (name, email, phone, pincode, address, password) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$name, $email, $phone, $pincode, $address, $hash]);
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['customer_id'] = $db->lastInsertId();
                $_SESSION['customer_name'] = $name;
                $_SESSION['customer_pincode'] = $pincode;
                header('Location: home.php');
                exit;
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
    <div class="tab-row">
      <button class="tab-btn active" onclick="switchTab('login', this)">Login</button>
      <button class="tab-btn" onclick="switchTab('register', this)">Sign Up</button>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
      <div class="demo-note"><strong>Demo:</strong> customer@test.com / password</div>
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
            <input type="password" name="reg_password" placeholder="Min 6 chars" required>
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