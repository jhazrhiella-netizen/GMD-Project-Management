<?php
require_once __DIR__ . '/../src/config.php';
header_remove();
$out = [
    'SUPABASE_URL' => getenv('SUPABASE_URL'),
    'SUPABASE_KEY_present' => getenv('SUPABASE_KEY') ? true : false,
    'SUPABASE_ANON_KEY_present' => getenv('SUPABASE_ANON_KEY') ? true : false,
];
// Try a simple GET to profiles
$res = sb_get_table('projects');
$out['sb_get_table_projects'] = $res;
echo json_encode($out, JSON_PRETTY_PRINT);
