<?php
// Sidebar include
require_once __DIR__ . '/../config.php';
$user = get_current_user();
?>
<div class="app-sidebar">
    <div style="font-weight:600;margin-bottom:12px">Admin</div>
    <a class="nav-link" href="/src/admin-pages/dashboard.php">Dashboard</a>
    <a class="nav-link" href="/src/modules/admin-modules/project-list.php">Projects</a>
    <a class="nav-link" href="/src/admin-pages/employees.php">Employees</a>
    <a class="nav-link" href="/src/admin-pages/admin-messages.php">Messages</a>
    <a class="nav-link" href="/src/modules/admin-modules/materials-list.php">Materials</a>
    <div style="margin-top:12px;font-size:13px;color:#666">Signed in as <?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
</div>
