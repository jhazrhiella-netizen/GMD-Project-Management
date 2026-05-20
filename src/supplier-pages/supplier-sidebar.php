<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_login();
$user = get_current_user();
?>
<style>
    .app-sidebar{width:240px;position:fixed;left:0;top:56px;bottom:0;background:#ffffff;border-right:1px solid #eef2f7;padding:16px;overflow:auto;transition:transform .25s ease,box-shadow .25s ease}
    .sidebar-header{font-weight:700;margin-bottom:12px;color:#0f172a}
    .nav-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px}
    .nav-list a{display:block;padding:8px 10px;border-radius:8px;color:#0f172a;text-decoration:none}
    .nav-list a:hover{background:#f1f5f9}
    .nav-list a.active{background:#e6f0ff;color:#0369a1;font-weight:600}
    .sidebar-footer{position:absolute;bottom:12px;left:16px;right:16px}
    @media (max-width:900px){ .app-sidebar{transform:translateX(-300px);z-index:1200;box-shadow:0 10px 30px rgba(2,6,23,0.08)} html.sidebar-open .app-sidebar{transform:translateX(0)} }
</style>
<?php
$cur = $_SERVER['REQUEST_URI'] ?? '';
function is_active($cur, $path){ return strpos($cur, $path) !== false ? 'active' : ''; }
?>
<div id="supplierSidebar" class="app-sidebar">
    <div class="sidebar-header">Hello, <?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
    <nav>
        <ul class="nav-list">
            <li><a class="<?php echo is_active($cur, 'supplier-dashboard.php'); ?>" href="/src/supplier-pages/supplier-dashboard.php">Dashboard</a></li>
            <li><a class="<?php echo is_active($cur, 'supplier-requests.php'); ?>" href="/src/supplier-pages/supplier-requests.php">Requests</a></li>
            <li><a class="<?php echo is_active($cur, 'supplier-messages.php'); ?>" href="/src/supplier-pages/supplier-messages.php">Messages</a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <a href="/src/logout.php" style="color:#0f172a;text-decoration:none">Logout</a>
    </div>
</div>