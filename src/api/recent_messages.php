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
if (!$user_id) { echo json_encode([]); exit; }

$limit = 6;
$res = sb_get_table('messages', 'recipient_id=eq.' . urlencode($user_id) . '&order=created_at.desc&limit=' . $limit);
$out = [];
if (isset($res['body']) && is_array($res['body'])) {
    foreach ($res['body'] as $m) {
        $out[] = [
            'id' => $m['id'] ?? null,
            'from' => $m['sender_id'] ?? $m['from'] ?? null,
            'content' => mb_strimwidth($m['content'] ?? ($m['message'] ?? ''), 0, 160, '...'),
            'created_at' => $m['created_at'] ?? null,
            'is_read' => $m['read'] ?? $m['is_read'] ?? ($m['status'] === 'read')
        ];
    }
}
echo json_encode($out);
exit;
