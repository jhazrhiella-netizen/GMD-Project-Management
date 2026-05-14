<?php
require_once __DIR__ . '/config.php';
// destroy session and redirect to login
session_unset();
session_destroy();
header('Location: /src/login.php');
exit;

?>