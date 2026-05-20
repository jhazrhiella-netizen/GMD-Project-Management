<?php
require_once __DIR__ . '/../config.php';
require_login();
$user = get_current_user();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <script src="/src/js/main.js" defer></script>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="app-container">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="app-main">
            <h2>Welcome, <?php echo htmlspecialchars($user['email'] ?? 'Admin'); ?></h2>
            <?php
            // include all dashboard modules found in modules/admin-dashboard-modules
            $modsDir = __DIR__ . '/../modules/admin-dashboard-modules';
            if (is_dir($modsDir)) {
                $files = scandir($modsDir);
                foreach ($files as $f) {
                    if (in_array($f, ['.','..'])) continue;
                    include $modsDir . '/' . $f;
                }
            }
            ?>
        </div>
    </div>
</body>
</html>
