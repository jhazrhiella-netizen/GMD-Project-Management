<?php
// check_env.php — safe environment health checks
// Access: localhost only, or provide GET token that matches CHECK_ENV_TOKEN env var

header('Content-Type: application/json');

$allowed = php_sapi_name() === 'cli' || (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']));
$token = isset($_GET['token']) ? $_GET['token'] : null;
$check_token = getenv('CHECK_ENV_TOKEN') ?: null;
if (! $allowed) {
    if ($check_token && $token && hash_equals($check_token, $token)) {
        $allowed = true;
    }
}
if (! $allowed) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden", "message" => "Access restricted to localhost or valid token."], JSON_PRETTY_PRINT);
    exit;
}

$supabase_url = getenv('SUPABASE_URL') ?: null;
$supabase_anon = getenv('SUPABASE_ANON_KEY') ?: null;
$supabase_key = getenv('SUPABASE_KEY') ?: null;
$public_storage = getenv('SUPABASE_PUBLIC_STORAGE');

$checks = [
    'SUPABASE_URL' => ['present' => (bool)$supabase_url],
    'SUPABASE_ANON_KEY' => ['present' => (bool)$supabase_anon],
    'SUPABASE_KEY' => ['present' => (bool)$supabase_key],
    'SUPABASE_PUBLIC_STORAGE' => ['value' => $public_storage !== false ? $public_storage : null],
];

if ($supabase_url && $supabase_anon) {
    $test_url = rtrim($supabase_url, '/') . '/rest/v1/profiles?select=id&limit=1';
    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabase_anon,
        'Authorization: Bearer ' . $supabase_anon,
        'Accept: application/json'
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $checks['anon_read'] = [
        'ok' => ($resp !== false && $code >= 200 && $code < 300),
        'http_code' => $code,
        'error' => $err ? $err : null
    ];
} else {
    $checks['anon_read'] = ['ok' => false, 'http_code' => null, 'error' => 'missing url or anon key'];
}

$checks['note'] = 'This endpoint never prints secret values. Use only from localhost or provide CHECK_ENV_TOKEN.';

echo json_encode(['ok' => true, 'checks' => $checks, 'ts' => date(DATE_ATOM)], JSON_PRETTY_PRINT);
