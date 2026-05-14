<?php
require_once __DIR__ . '/../config.php';
require_login();
// Simple wrapper: embed the existing manage-requests module in an iframe to keep layout
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Supplier Requests</title>
	<link rel="stylesheet" href="/src/css/styles.css">
	<style>
	.supplier-main { margin-left:240px; max-width:1100px; padding:16px }
	iframe.requests-embed { width:100%; height:80vh; border:0 }
	</style>
</head>
<body>
	<?php include __DIR__ . '/supplier-header.php'; ?>
	<?php include __DIR__ . '/supplier-sidebar.php'; ?>

	<div class="supplier-main">
		<h2>Requests</h2>
		<div class="card">
			<iframe class="requests-embed" src="/src/modules/supplier-modules/manage-requests.php"></iframe>
		</div>
	</div>
</body>
</html>