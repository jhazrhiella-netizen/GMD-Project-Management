<?php
require_once __DIR__ . '/../../config.php';
require_login();

$q = trim($_GET['q'] ?? '');
header('Content-Type: application/json');
if ($q === '') {
    echo json_encode(['ok' => true, 'body' => []]);
    exit;
}

// Use ilike for case-insensitive partial match on name
$enc = rawurlencode('%' . $q . '%');
$path = 'name=ilike.' . $enc . '&select=id,name,contact,email,address,client_region_code,client_province_code,client_city_code,client_barangay_code,client_region_name,client_province_name,client_city_name,client_barangay_name';
$res = sb_get_table('clients', $path);
echo json_encode($res);

?>