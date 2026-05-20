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
<style>
    .app-header{background:#1e40af;color:#fff;position:fixed;top:0;left:0;right:0;height:56px;z-index:1500}
    .header-inner{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;padding:8px 12px;height:100%}
    .brand{display:flex;align-items:center;gap:12px;font-weight:700;font-size:16px}
    .btn-reset{background:transparent;border:0;color:inherit;font:inherit;cursor:pointer}
    .header-actions{display:flex;align-items:center;gap:12px}
    .badge{background:#ef4444;color:#fff;border-radius:12px;padding:2px 8px;font-size:12px;margin-left:6px}
    .messages-dropdown{display:none;position:absolute;right:0;top:36px;background:#fff;color:#000;border:1px solid #e6e6e6;width:320px;box-shadow:0 10px 30px rgba(2,6,23,0.12);z-index:2000;border-radius:6px;overflow:hidden}
    .messages-dropdown .title{padding:10px 12px;border-bottom:1px solid #f2f2f2;font-weight:600}
    .messages-dropdown .list{max-height:260px;overflow:auto}
    .messages-dropdown .item{padding:10px;border-bottom:1px solid #f8f8f8}
    @media (max-width:900px){ .header-inner{padding:8px} }
</style>
<div class="app-header">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
    <div class="header-inner">
        <div class="brand">
            <button id="sidebarToggle" class="btn-reset" aria-label="Toggle sidebar">☰</button>
            <div>Supplier Portal</div>
        </div>
        <div class="header-actions">
            <div id="clock" style="font-family:monospace"></div>
            <div><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
            <form method="get" action="/src/modules/supplier-modules/supplier-lockscreen.php" style="display:inline">
                <input type="hidden" name="action" value="lock" />
                <button type="submit" class="btn-reset" style="padding:6px 8px;border:1px solid rgba(255,255,255,0.12);border-radius:6px;color:#fff">Lock</button>
            </form>
            <div style="position:relative">
                <button id="messagesToggle" class="btn-reset" aria-haspopup="true" aria-expanded="false">Messages<?php if($unreadCount>0) echo ' <span class="badge">' . intval($unreadCount) . '</span>'; ?></button>
                <div id="messagesDropdown" class="messages-dropdown" aria-hidden="true">
                    <div class="title">Messages</div>
                    <div id="messagesList" class="list"><div style="padding:12px;color:#666">Loading…</div></div>
                    <div style="padding:10px;border-top:1px solid #f2f2f2;text-align:center"><a href="/src/supplier-pages/supplier-messages.php">View all</a></div>
                </div>
            </div>
            <a href="/src/logout.php" style="color:#fff;text-decoration:none">Logout</a>
        </div>
    </div>
</div>
<script>
function updateClock(){
    var d=new Date();
    var el = document.getElementById('clock'); if(el) el.textContent = d.toLocaleString();
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
        overlayEl.style.left = 0; overlayEl.style.top = '56px'; overlayEl.style.right = 0; overlayEl.style.bottom = 0;
        overlayEl.style.background = 'rgba(0,0,0,0.22)';
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
var sidebarBtn = document.getElementById('sidebarToggle');
if(sidebarBtn) sidebarBtn.addEventListener('click', function(e){ if (sidebarOpen) closeSidebar(); else openSidebar(); });
// hide sidebar when clicking internal links
document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a');
    if (a && a.getAttribute('href') && a.getAttribute('href').indexOf('/src/')===0){ closeSidebar(); }
});

// Messages dropdown: fetch previews and unread count
var messagesToggle = document.getElementById('messagesToggle');
var messagesDropdown = document.getElementById('messagesDropdown');
var messagesList = document.getElementById('messagesList');
function fetchMessagesPreview(){
    fetch('/src/api/recent_messages.php').then(r=>r.json()).then(data=>{
        messagesList.innerHTML = '';
        if (!data || data.length===0) { messagesList.innerHTML = '<div style="padding:12px;color:#666">No messages</div>'; return; }
        data.forEach(function(m){
            var el = document.createElement('div');
            el.className = 'item';
            el.innerHTML = '<div style="font-size:13px;color:#222">'+(m.from||'')+'</div><div style="font-size:12px;color:#555">'+(m.content||'')+'</div><div style="font-size:11px;color:#999;margin-top:6px">'+(m.created_at||'')+'</div>';
            messagesList.appendChild(el);
        });
    }).catch(e=>{ messagesList.innerHTML = '<div style="padding:12px;color:#a00">Error loading</div>'; });
}
function refreshUnreadCount(){
    fetch('/src/api/unread_count.php').then(r=>r.json()).then(j=>{
        if (j && typeof j.count !== 'undefined'){
            var badge = messagesToggle.querySelector('.badge');
            if (j.count>0){ if(!badge){ var sp = document.createElement('span'); sp.className='badge'; sp.textContent=j.count; messagesToggle.appendChild(sp);} else badge.textContent=j.count; }
            else if(badge) badge.remove();
        }
    }).catch(()=>{});
}
if(messagesToggle){
    messagesToggle.addEventListener('click', function(e){
        e.stopPropagation();
        if (messagesDropdown.style.display === 'block') { messagesDropdown.style.display='none'; messagesToggle.setAttribute('aria-expanded','false'); }
        else { messagesDropdown.style.display='block'; messagesToggle.setAttribute('aria-expanded','true'); fetchMessagesPreview(); }
    });
}
document.addEventListener('click', function(e){ if (!e.target.closest || !e.target.closest('#messagesDropdown')) { if(messagesDropdown) messagesDropdown.style.display='none'; if(messagesToggle) messagesToggle.setAttribute('aria-expanded','false'); } });
setInterval(refreshUnreadCount, 30000);
refreshUnreadCount();
</script>