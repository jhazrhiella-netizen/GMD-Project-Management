<?php
require 'src/load_env.php';
require 'supabase-connection.php';
$res = sb_sign_in('admin@sample.com','adminadmin');
echo json_encode($res, JSON_PRETTY_PRINT);
