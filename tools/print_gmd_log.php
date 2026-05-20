<?php
// Print last lines of gmd_add_project.log in system temp dir
$f = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gmd_add_project.log';
if (!file_exists($f)) {
    echo "MISSING:" . $f . PHP_EOL;
    exit(0);
}
$lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$start = max(0, count($lines) - 200);
for ($i = $start; $i < count($lines); $i++) echo $lines[$i] . PHP_EOL;

?>