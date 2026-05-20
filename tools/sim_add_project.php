<?php
// Simulate an AJAX POST to add-project.php and capture output
chdir(__DIR__ . '/..');
require_once 'src/config.php';
// ensure session and csrf
if (!function_exists('generate_csrf_token')) require_once 'src/config.php';
$csrf = generate_csrf_token();
// fake login
$_SESSION['gmd_user'] = ['id'=>'cli-test-user','email'=>'cli@example.com'];
// prepare POST data
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST = [
    '_csrf' => $csrf,
    'add_project' => '1',
    'project_name' => 'Test Project CLI',
    'client_name' => 'CLI Client',
    'budget' => '1000',
    'start_date' => date('Y-m-d'),
    'lead_time' => '30',
    'client_contact' => '000',
    'client_email' => 'cli@example.com'
];
ob_start();
include 'src/modules/admin-modules/add-project.php';
$out = ob_get_clean();
// print response and headers info if available
echo "OUTPUT:\n";
echo $out . PHP_EOL;
// attempt to decode JSON
$json = json_decode($out, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "DECODED JSON:\n" . json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo "JSON ERROR: " . json_last_error_msg() . PHP_EOL;
}
