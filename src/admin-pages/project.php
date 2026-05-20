<?php
require_once __DIR__ . '/../config.php';
require_login();

?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Projects - Admin</title>
	<link rel="stylesheet" href="/src/css/styles.css">
	<script src="/src/js/main.js" defer></script>
	<style>.flex-row{display:flex;gap:16px}.flex-1{flex:1}</style>
</head>
<body>
	<?php include __DIR__ . '/header.php'; ?>
	<div class="app-container">
		<?php include __DIR__ . '/sidebar.php'; ?>
		<div class="app-main">
			<h2>Projects</h2>
			<div class="flex-row">
				<div class="flex-1">
					<?php include __DIR__ . '/../modules/admin-modules/project-list.php'; ?>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
