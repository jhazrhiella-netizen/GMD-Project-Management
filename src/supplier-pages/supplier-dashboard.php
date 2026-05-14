<?php
require_once __DIR__ . '/../config.php';
require_login();
$user = get_current_user();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Dashboard</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <style>
    .supplier-main { margin-left:240px; max-width:1100px; padding:16px }
    </style>
</head>
<body>
    <?php include __DIR__ . '/supplier-header.php'; ?>
    <?php include __DIR__ . '/supplier-sidebar.php'; ?>

    <div class="supplier-main">
        <h2>Supplier Dashboard</h2>
        <div class="card">
            <h3>Welcome, <?php echo htmlspecialchars($user['email'] ?? 'Supplier'); ?></h3>
            <p>Quick links</p>
            <ul>
                <li><a href="/src/modules/supplier-modules/manage-requests.php">Manage Requests</a></li>
                <li><a href="/src/supplier-pages/supplier-messages.php">Messages</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
