<?php
// Header include with time, notifications, lock and sidebar toggle
require_once __DIR__ . '/../config.php';
$user = get_current_user();
$unreadCount = 0;
$notifications = [];
// fetch unread messages as notifications (use `recipient_id` column)
$user_id = $user['id'] ?? null;
if (!empty($user_id)) {
    $res = sb_get_table('messages', 'recipient_id=eq.' . urlencode($user_id) . '&status=neq.read&select=sender_id,content,created_at&order=created_at.desc');
    if (isset($res['body']) && is_array($res['body'])) {
        $notifications = $res['body'];
        $unreadCount = count($notifications);
    }
}
?>
<div class="app-header">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
    <div style="display:flex;align-items:center;justify-content:space-between;max-width:1100px;margin:0 auto">
        <div style="display:flex;align-items:center;gap:12px">
            <button id="sidebarToggle" style="background:transparent;border:none;color:#fff;cursor:pointer">☰</button>
            <div><strong>GMD South Phils</strong> — Project Management</div>
            <div id="headerTime" style="margin-left:12px;color:#e6f0ff;font-size:13px"></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <div style="position:relative">
                <button id="notifBtn" style="background:transparent;border:none;color:#fff;cursor:pointer">🔔 <span id="notifCount" style="font-weight:700"><?php echo $unreadCount? '('.$unreadCount.')':''; ?></span></button>
                <div id="notifDropdown" style="display:none;position:absolute;right:0;top:28px;background:#fff;color:#000;padding:8px;border-radius:6px;box-shadow:0 8px 20px rgba(0,0,0,.12);width:320px;max-height:300px;overflow:auto">
                    <?php if (empty($notifications)): ?>
                        <div style="padding:8px">No notifications</div>
                    <?php else: ?>
                        <?php foreach($notifications as $n): ?>
                            <div style="padding:8px;border-bottom:1px solid #eee">
                                <div style="font-size:13px"><?php echo htmlspecialchars($n['content'] ?? ''); ?></div>
                                <div style="font-size:11px;color:#666"><?php echo htmlspecialchars($n['created_at'] ?? ''); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <a style="color:#fff;text-decoration:none;margin-right:12px" href="/src/modules/admin-modules/lockscreen.php?action=lock">Lock</a>
            <div style="position:relative">
                <button id="profileBtn" style="background:transparent;border:none;color:#fff;cursor:pointer"><?php echo htmlspecialchars($user['email'] ?? 'Profile'); ?></button>
                <div id="profileDropdown" style="display:none;position:absolute;right:0;top:28px;background:#fff;color:#000;padding:8px;border-radius:6px;box-shadow:0 8px 20px rgba(0,0,0,.12);width:200px">
                    <div style="padding:8px;font-weight:700"><?php echo htmlspecialchars($user['profile']['full_name'] ?? $user['email'] ?? ''); ?></div>
                    <div style="padding:8px"><a href="/src/admin-pages/employees.php">Profile</a></div>
                    <div style="padding:8px"><a href="/src/logout.php">Logout</a></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function updateTime(){
    var d=new Date();
    document.getElementById('headerTime').textContent = d.toLocaleString();
}
setInterval(updateTime,1000);updateTime();
document.getElementById('notifBtn').addEventListener('click',function(){
    var el=document.getElementById('notifDropdown'); el.style.display = el.style.display==='none'?'block':'none';
});
document.getElementById('profileBtn').addEventListener('click',function(){
    var el=document.getElementById('profileDropdown'); el.style.display = el.style.display==='none'?'block':'none';
});
document.getElementById('sidebarToggle').addEventListener('click',function(){
    document.body.classList.toggle('sidebar-collapsed');
});
</script>
