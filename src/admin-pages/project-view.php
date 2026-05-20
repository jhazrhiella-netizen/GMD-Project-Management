<?php
require_once __DIR__ . '/../config.php';
require_login();

$proj_id = $_GET['id'] ?? null;
if (!$proj_id) {
	header('Location: /src/modules/admin-modules/project-list.php');
	exit;
}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Project View</title>
	<link rel="stylesheet" href="/src/css/styles.css">
</head>
<body>
	<?php include __DIR__ . '/header.php'; ?>
	<div class="app-container">
		<?php include __DIR__ . '/sidebar.php'; ?>
		<div class="app-main">
			<?php include __DIR__ . '/../modules/admin-modules/project-details.php'; ?>
			<?php include __DIR__ . '/../modules/admin-modules/project-progess.php'; ?>
			<?php include __DIR__ . '/../modules/admin-modules/materials-list.php'; ?>
		</div>
	</div>
</body>
</html>
