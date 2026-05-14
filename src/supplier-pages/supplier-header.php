<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_login();
// Header snippet for supplier pages — expects `get_current_user()` to be available
$user = function_exists('get_current_user') ? get_current_user() : null;
// unread messages count
$unreadCount = 0;
$msgsRes = sb_get_table('messages', 'recipient_id=eq.' . urlencode($user['id'] ?? ''));
if (isset($msgsRes['body']) && is_array($msgsRes['body'])) {
    foreach ($msgsRes['body'] as $mm) {
        if (isset($mm['read']) && ($mm['read'] === false || $mm['read'] === 'f' || $mm['read'] === 0)) { $unreadCount++; continue; }
        if (isset($mm['is_read']) && ($mm['is_read'] === false || $mm['is_read'] === 'f' || $mm['is_read'] === 0)) { $unreadCount++; continue; }
        if (isset($mm['status']) && $mm['status'] === 'unread') { $unreadCount++; continue; }
    }
}
?>
<div class="app-header" style="background:#2b6cb0;color:#fff">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
    <div style="max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;padding:8px 0">
        <div style="display:flex;align-items:center;gap:12px">
            <button id="sidebarToggle" style="background:transparent;border:0;color:#fff;font-size:20px">☰</button>
            <div style="font-weight:600">Supplier Portal</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <div id="clock" style="font-family:monospace"></div>
            <div><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
            <form method="get" action="/src/modules/supplier-modules/supplier-lockscreen.php" style="display:inline">
                <input type="hidden" name="action" value="lock" />
                <button type="submit" style="background:transparent;border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 8px;border-radius:4px">Lock</button>
            </form>
            <div style="position:relative">
                <button id="messagesToggle" style="background:transparent;border:0;color:#fff;position:relative">Messages<?php if($unreadCount>0) echo ' (' . intval($unreadCount) . ')'; ?></button>
                <div id="messagesDropdown" style="display:none;position:absolute;right:0;top:28px;background:#fff;color:#000;border:1px solid #ddd;width:320px;box-shadow:0 6px 18px rgba(0,0,0,0.08);z-index:2000">
                    <div style="padding:8px;border-bottom:1px solid #eee;font-weight:600">Messages</div>
                    <div id="messagesList" style="max-height:260px;overflow:auto"></div>
                    <div style="padding:8px;border-top:1px solid #eee;text-align:center"><a href="/src/supplier-pages/supplier-messages.php">View all</a></div>
                </div>
            </div>
            <a href="/src/logout.php" style="color:#fff;text-decoration:none">Logout</a>
        </div>
    </div>
</div>
<script>
function updateClock(){
    var d=new Date();
    document.getElementById('clock').textContent = d.toLocaleString();
}
updateClock(); setInterval(updateClock,1000);
var sidebarOpen = false;
var overlayEl = null;
function openSidebar(){
    document.documentElement.classList.add('sidebar-open');
    sidebarOpen = true;
    if (!overlayEl){
        overlayEl = document.createElement('div');
        overlayEl.id = 'sidebarOverlay';
        overlayEl.style.position = 'fixed';
        overlayEl.style.left = 0; overlayEl.style.top = 56 + 'px'; overlayEl.style.right = 0; overlayEl.style.bottom = 0;
        overlayEl.style.background = 'rgba(0,0,0,0.2)';
        overlayEl.addEventListener('click', closeSidebar);
        document.body.appendChild(overlayEl);
    } else {
        overlayEl.style.display = 'block';
    }
}
function closeSidebar(){
    document.documentElement.classList.remove('sidebar-open');
    sidebarOpen = false;
    if (overlayEl) overlayEl.style.display = 'none';
}
document.getElementById('sidebarToggle').addEventListener('click', function(e){
    if (sidebarOpen) closeSidebar(); else openSidebar();
});
// hide sidebar when clicking internal links
document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a');
    if (a && a.getAttribute('href') && a.getAttribute('href').indexOf('/src/')===0){
        closeSidebar();
    }
});
// Messages dropdown behavior: fetch previews and unread count
var messagesToggle = document.getElementById('messagesToggle');
var messagesDropdown = document.getElementById('messagesDropdown');
var messagesList = document.getElementById('messagesList');
function fetchMessagesPreview(){
    fetch('/src/api/recent_messages.php').then(r=>r.json()).then(data=>{
        messagesList.innerHTML = '';
        if (!data || data.length===0) { messagesList.innerHTML = '<div style="padding:12px;color:#666">No messages</div>'; return; }
        data.forEach(function(m){
            var el = document.createElement('div');
            el.style.padding='8px'; el.style.borderBottom='1px solid #f2f2f2';
            el.innerHTML = '<div style="font-size:13px;color:#222">'+(m.from||'')+'</div><div style="font-size:12px;color:#555">'+(m.content||'')+'</div><div style="font-size:11px;color:#999;margin-top:6px">'+(m.created_at||'')+'</div>';
            messagesList.appendChild(el);
        });
    }).catch(e=>{ messagesList.innerHTML = '<div style="padding:12px;color:#a00">Error loading</div>'; });
}
function refreshUnreadCount(){
    fetch('/src/api/unread_count.php').then(r=>r.json()).then(j=>{
        if (j && typeof j.count !== 'undefined'){
            messagesToggle.textContent = 'Messages' + (j.count>0?(' ('+j.count+')'):'');
        }
    }).catch(()=>{});
}
messagesToggle.addEventListener('click', function(e){
    e.stopPropagation();
    if (messagesDropdown.style.display === 'block') { messagesDropdown.style.display='none'; }
    else { messagesDropdown.style.display='block'; fetchMessagesPreview(); }
});
document.addEventListener('click', function(e){ if (!e.target.closest || !e.target.closest('#messagesDropdown')) messagesDropdown.style.display='none'; });
// refresh count periodically
setInterval(refreshUnreadCount, 30000);
refreshUnreadCount();
</script>
This will be the header used for the supplier pages. This will include the name of the supplier. Just like the admin header, this will have a date and time display and a lockscreen button to lock the screen if the supplier needs to step away from their computer. 