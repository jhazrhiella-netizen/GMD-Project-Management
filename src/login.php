<?php
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = $_POST['email'] ?? '';
	$password = $_POST['password'] ?? '';
	if (!$email || !$password) {
		$error = 'Enter email and password.';
	} else {
		$res = auth_sign_in($email, $password);
		if (isset($res['error'])) {
			$error = $res['error'];
		} else {
			$_SESSION['gmd_user'] = $res['user'];
			// regenerate session id to prevent fixation
			session_regenerate_id(true);
			// set login timestamps and expiry (8 hours)
			$_SESSION['gmd_user']['login_at'] = time();
			$_SESSION['gmd_user']['expires'] = time() + (8 * 3600);
			// ensure a CSRF token exists for forms
			if (function_exists('generate_csrf_token')) generate_csrf_token();
			$role = $res['user']['role'] ?? 'admin';
			if ($role === 'supplier') {
				header('Location: /src/supplier-pages/supplier-dashboard.php');
				exit;
			}
			header('Location: /src/admin-pages/dashboard.php');
			exit;
		}
	}
}

?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>GMD - Login</title>
	<link rel="stylesheet" href="/src/css/styles.css">
</head>
<body class="login-page">
	<div class="login-card">
		<h1>GMD Login</h1>
		<?php if ($error): ?>
			<div class="error"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<form method="post">
			<label>Email</label>
			<input type="email" name="email" required />
			<label>Password</label>
			<input type="password" name="password" required />
			<button type="submit">Login</button>
		</form>
	</div>
</body>
</html>
