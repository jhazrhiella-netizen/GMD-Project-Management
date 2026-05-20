<?php
require_once __DIR__ . '/../src/config.php';
header_remove();
$res = sb_get_table('clients');
echo json_encode($res, JSON_PRETTY_PRINT);

?>