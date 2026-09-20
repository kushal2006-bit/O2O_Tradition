<?php
// Copy this file to shared/config.php for local development.
// Never commit real database credentials.

define('DB_HOST', getenv('O2O_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('O2O_DB_NAME') ?: 'o2o_tradition');
define('DB_USER', getenv('O2O_DB_USER') ?: 'root');
define('DB_PASS', getenv('O2O_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// AI provider configuration (never commit real keys).
// OpenAI-backed features can use OPENAI_API_KEY when enabled.
// Free virtual try-on uses HF_TOKEN for the public IDM-VTON ZeroGPU Space.
define('O2O_AI_MODEL', getenv('O2O_AI_MODEL') ?: 'gpt-5.6-luna');
// HF_TOKEN is intentionally read directly from the environment by customer/tryon.php.

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('O2O Tradition database connection failed: ' . $e->getMessage());
            die('Database connection failed. Check your local configuration.');
        }
    }
    return $pdo;
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/items/');
define('UPLOAD_URL', '../uploads/items/');

function isLoggedIn($role = 'customer') {
    return isset($_SESSION[$role . '_id']);
}

function requireLogin($role = 'customer', $redirect = 'login.php') {
    if (!isLoggedIn($role)) {
        header("Location: $redirect");
        exit;
    }
}
?>