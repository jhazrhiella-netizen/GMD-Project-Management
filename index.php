<?php
require __DIR__ . '/src/config.php';

// Simple routing: if logged in, go to dashboard for role; otherwise go to login
if (is_logged_in()) {
	$user = get_current_user() ?: [];
	$role = $user['role'] ?? 'admin';
	if ($role === 'supplier') {
		header('Location: src/supplier-pages/supplier-dashboard.php');
		exit;
	}
	header('Location: src/admin-pages/dashboard.php');
	exit;
}

header('Location: src/login.php');
exit;

?>