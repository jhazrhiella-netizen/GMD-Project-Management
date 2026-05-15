<?php
// Basic app bootstrap and helpers
// Load local .env for development if present (safe: does not override existing env)
require_once __DIR__ . '/load_env.php';
// Toggle debug output via APP_ENV=development or DEBUG=1
$debug = (getenv('APP_ENV') === 'development' || getenv('DEBUG') === '1');
if ($debug) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
	error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
}

// Session hardening - configure cookie params before starting session
$forceSecure = (getenv('FORCE_SECURE_SESSION') === '1') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
	'lifetime' => 0,
	'secure' => $forceSecure,
	'httponly' => true,
	'samesite' => 'Lax'
]);
session_start();

require_once __DIR__ . '/../supabase-connection.php';
require_once __DIR__ . '/auth.php';

// verify CSRF token from request (header or POST field)
if (!function_exists('verify_request_csrf')) {
	function verify_request_csrf() {
		// prefer header 'X-CSRF-Token'
		$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? null;
		if (!$token && isset($_POST['_csrf'])) $token = $_POST['_csrf'];
		if (!$token && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			// no token provided
			return false;
		}
		if (!$token) return false;
		return function_exists('verify_csrf_token') && verify_csrf_token($token);
	}
}

if (!function_exists('is_logged_in')) {
	function is_logged_in() {
		if (empty($_SESSION['gmd_user'])) return false;
		// check expiry if set
		$exp = $_SESSION['gmd_user']['expires'] ?? null;
		if ($exp !== null && is_numeric($exp) && time() > intval($exp)) {
			// session expired
			unset($_SESSION['gmd_user']);
			return false;
		}
		return true;
	}
}

if (!function_exists('get_current_user')) {
	function get_current_user() {
		return $_SESSION['gmd_user'] ?? null;
	}
}

if (!function_exists('require_login')) {
	function require_login() {
		if (!is_logged_in()) {
			// Detect API / AJAX requests and return JSON 401 instead of redirect
			$isApi = false;
			$reqUri = $_SERVER['REQUEST_URI'] ?? '';
			$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
			$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
			if (strpos($reqUri, '/src/api/') === 0 || stripos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest') {
				http_response_code(401);
				header('Content-Type: application/json');
				echo json_encode(['error' => 'Unauthorized']);
				exit;
			}
			header('Location: /src/login.php');
			exit;
		}
	}
}

// CSRF helpers
if (!function_exists('generate_csrf_token')) {
	function generate_csrf_token() {
		if (!isset($_SESSION['csrf_token'])) {
			try {
				$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
				$_SESSION['csrf_token_time'] = time();
			} catch (Exception $e) {
				$_SESSION['csrf_token'] = md5(uniqid('', true));
				$_SESSION['csrf_token_time'] = time();
			}
		}
		return $_SESSION['csrf_token'];
	}
}

if (!function_exists('verify_csrf_token')) {
	function verify_csrf_token($token) {
		if (empty($token) || empty($_SESSION['csrf_token'])) return false;
		// Optionally expire tokens after 2 hours
		$t = $_SESSION['csrf_token_time'] ?? 0;
		if ($t && (time() - $t) > 7200) {
			unset($_SESSION['csrf_token']);
			unset($_SESSION['csrf_token_time']);
			return false;
		}
		return hash_equals($_SESSION['csrf_token'], $token);
	}
}

?>