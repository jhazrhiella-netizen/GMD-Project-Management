<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
require_login();
// For state-changing requests enforce CSRF; GETs (reads) are allowed without token
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    if (!function_exists('verify_request_csrf') || !verify_request_csrf()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}
$user = get_current_user();
$user_id = $user['id'] ?? null;
if (!$user_id) { echo json_encode(['count' => 0]); exit; }

$res = sb_get_table('messages', 'recipient_id=eq.' . urlencode($user_id), ['Prefer: count=exact']);
$count = 0;
if (isset($res['headers']['content-range'])) {
    $cr = $res['headers']['content-range'];
    if (strpos($cr, '/') !== false) {
        $parts = explode('/', $cr);
        $count = intval($parts[1]);
    }
}
if ($count === 0 && isset($res['body']) && is_array($res['body'])) $count = count($res['body']);
echo json_encode(['count' => $count]);
exit;
