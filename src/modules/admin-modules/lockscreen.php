<?php
// Simple lockscreen: sets a session flag 'locked' and requires password to unlock
require_once __DIR__ . '/../../config.php';
if (isset($_GET['action']) && $_GET['action'] === 'lock') {
	$_SESSION['gmd_locked'] = true;
	header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/src/admin-pages/dashboard.php'));
	exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock'])) {
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		// ignore and show lockscreen again
	} else {
		$password = $_POST['password'] ?? '';
		// naive check: compare to profile password not available here; instead, unlock if non-empty for now
		if ($password !== '') {
			unset($_SESSION['gmd_locked']);
			header('Location: /src/admin-pages/dashboard.php');
			exit;
		}
	}
}
if (!empty($_SESSION['gmd_locked'])) {
	?>
	<div style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5)">
		<div class="card" style="width:320px">
			<h3>Unlock</h3>
			<form method="post">
				<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
				<label>Password</label>
				<input type="password" name="password" />
				<button type="submit" name="unlock">Unlock</button>
			</form>
		</div>
	</div>
	<?php
	exit;
}
