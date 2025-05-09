<?php
// Start output buffering
ob_start();

// Debug logging function
function debug_log($message) {
    $log_file = __DIR__ . '/../logs/auth_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    error_log($log_message, 3, $log_file);
}

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Basic session configuration
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '3600');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Initialize $_SESSION if not set
if (!isset($_SESSION)) {
    $_SESSION = array();
}

debug_log("Session Information:");
debug_log("Session ID: " . session_id());
debug_log("Session Data: " . print_r($_SESSION, true));
debug_log("Cookie Data: " . print_r($_COOKIE, true));

class Auth {
    private $db;    public function __construct($db) {
        $this->db = $db;
        
        // Initialize CSRF token if not exists
        if (!isset($_SESSION) || !isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            debug_log("New CSRF token generated: " . $_SESSION['csrf_token']);
        }
    }    public function generateCSRFToken() {
        if (!isset($_SESSION) || !isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            debug_log("Generating new CSRF token: " . $_SESSION['csrf_token']);
        }
        return $_SESSION['csrf_token'];
    }    public function validateCSRFToken($token) {
        debug_log("Validating CSRF token");
        debug_log("Session token: " . (isset($_SESSION) && isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'not set'));
        debug_log("Submitted token: " . (isset($token) ? $token : 'not set'));
        
        if (!isset($_SESSION) || !isset($_SESSION['csrf_token']) || !isset($token)) {
            debug_log("CSRF validation failed: Missing token");
            return false;
        }
        $result = hash_equals($_SESSION['csrf_token'], $token);
        debug_log("CSRF validation result: " . ($result ? 'true' : 'false'));
        return $result;
    }

    public function login($username, $password) {
        debug_log("Login attempt for username: " . $username);
        
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['user_type'] === 'locked') {
                debug_log("Login failed: Account is locked");
                return ['success' => false, 'message' => 'Account is locked'];
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            debug_log("Login successful for user: " . $user['username']);
            return ['success' => true, 'user_type' => $user['user_type']];
        }

        debug_log("Login failed: Invalid credentials");
        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }

    public function logout() {
        // Clear session data
        $_SESSION = array();
        
        // Destroy the session
        session_destroy();
        
        // Clear the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/eCommerce');
        }
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, username, user_type FROM users WHERE id = :id');
        $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
}
?> 