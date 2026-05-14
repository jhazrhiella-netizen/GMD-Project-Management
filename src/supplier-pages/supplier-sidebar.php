<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_login();
$user = get_current_user();
?>
<style>
/* responsive sidebar: hidden by default on small screens, revealed when html.sidebar-open is set */
.app-sidebar { width:220px;position:fixed;left:0;top:56px;bottom:0;background:#f7fafc;border-right:1px solid #e2e8f0;padding:12px;overflow:auto;transition:transform .25s ease }
@media (max-width:900px){
    .app-sidebar { transform: translateX(-260px); z-index:1000 }
    html.sidebar-open .app-sidebar { transform: translateX(0) }
}
</style>
<div id="supplierSidebar" class="app-sidebar">
    <div style="margin-bottom:12px;font-weight:600">Hello, <?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
    <nav>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px">
            <li><a href="/src/supplier-pages/supplier-dashboard.php">Dashboard</a></li>
            <li><a href="/src/modules/supplier-modules/manage-requests.php">Requests</a></li>
            <li><a href="/src/supplier-pages/supplier-messages.php">Messages</a></li>
        </ul>
    </nav>
    <div style="position:absolute;bottom:12px;left:12px;right:12px">
        <a href="/src/logout.php">Logout</a>
    </div>
</div>
This is the side navbar for the supplier pages. This will also include the logout button in the bottom part of the sidebar. The pages that will be listed in the sidebar are:
    - Dashboard
    - Requests
    - Messages


The side bars behaviour is a toggleable one. It will be hidden by default and will be shown when the user clicks on the hamburger menu icon in the top left corner of the page. The sidebar will be hidden again when the user clicks on the hamburger menu icon again or when the user clicks outside the sidebar. The sidebar will also be hidden when the user clicks on any of the links in the sidebar. This will ensure that the user has a clean and distraction-free experience when navigating through the supplier pages. The sidebar will also be responsive and will adjust its layout based on the screen size of the device being used. On smaller screens, the sidebar will take up the full width of the screen and will be displayed as a dropdown menu when the hamburger menu icon is clicked. On larger screens, the sidebar will be displayed as a vertical menu on the left side of the screen. This will ensure that the sidebar is accessible and user-friendly on all devices.