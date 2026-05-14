<?php
require_once __DIR__ . '/../config.php';
require_login();
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Employees</title>
	<link rel="stylesheet" href="/src/css/styles.css">
</head>
<body>
	<?php include __DIR__ . '/header.php'; ?>
	<div class="app-container">
		<?php include __DIR__ . '/sidebar.php'; ?>
		<div class="app-main">
			<h2>Employees</h2>
			<?php include __DIR__ . '/../modules/admin-modules/employee-list.php'; ?>
			<?php include __DIR__ . '/../modules/admin-modules/manage-salary.php'; ?>
		</div>
	</div>
</body>
</html>
