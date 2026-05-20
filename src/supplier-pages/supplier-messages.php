<?php
require_once __DIR__ . '/../config.php';
require_login();

$currentUser = get_current_user();
// ensure ids are strings to avoid passing null to urlencode()
$admin_id = $_GET['admin_id'] ?? '';

// handle sending a message or file (enhanced: sender resolution, duplicate protection, storage handling)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (
	isset($_POST['send_message']) || isset($_POST['send_file']) ||
	(!empty(trim($_POST['content'] ?? ''))) ||
	(!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK)
)) {
	error_log('supplier-messages: POST received keys: ' . json_encode(array_keys($_POST)) . ' hasFiles=' . (isset($_FILES) ? '1' : '0'));
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		$error = 'Invalid form submission.';
		error_log('supplier-messages: CSRF failed for session=' . session_id());
	} else {
		$to = $_POST['to'] ?? '';
		$content = trim($_POST['content'] ?? '');
		$msgType = 'text';

		// Attachment uploads are disabled on supplier side for now.
		// (Do not process $_FILES here — file input removed from form.)

		if ($to && $content) {
			// resolve sender_id reliably
			$sender_id = $currentUser['id'] ?? null;
			if (empty($sender_id) && !empty($_SESSION['gmd_user']['profile']['id'])) {
				$sender_id = $_SESSION['gmd_user']['profile']['id'];
			}
			if (empty($sender_id) && !empty($_SESSION['gmd_user']['email'])) {
				$probe = sb_get_table('profiles', 'email=eq.' . rawurlencode($_SESSION['gmd_user']['email']) . '&select=id');
				if (isset($probe['body']) && is_array($probe['body']) && count($probe['body'])>0) {
					$sender_id = $probe['body'][0]['id'] ?? $sender_id;
				}
			}
			if (empty($sender_id)) error_log('supplier-messages: could not determine sender_id: ' . json_encode($_SESSION['gmd_user'] ?? null));

			// duplicate protection: check last message from sender->to
			$skipInsert = false;
			if (!empty($sender_id)) {
				$lastRes = sb_get_table('messages', 'sender_id=eq.' . rawurlencode((string)$sender_id) . '&recipient_id=eq.' . rawurlencode((string)$to) . '&select=content,created_at&order=created_at.desc&limit=1');
				if (isset($lastRes['body']) && is_array($lastRes['body']) && count($lastRes['body'])>0) {
					$last = $lastRes['body'][0];
					$lastContent = $last['content'] ?? null;
					$lastTime = isset($last['created_at']) ? strtotime($last['created_at']) : false;
					if ($lastContent !== null && $lastContent === $content && $lastTime !== false && (time() - $lastTime) < 10) {
						$skipInsert = true;
						error_log('supplier-messages: skipped duplicate insert for sender=' . json_encode($sender_id) . ' recipient=' . json_encode($to));
					}
				}
			}

			if ($skipInsert) {
				header('Location: /src/supplier-pages/supplier-messages.php?admin_id=' . urlencode((string)$to));
				exit;
			}

			$data = [
				'sender_id' => $sender_id,
				'recipient_id' => $to,
				'content' => $content,
				'type' => $msgType,
				'status' => 'sent'
			];
			error_log('supplier-messages: inserting: ' . json_encode($data));
			$res = sb_insert_table('messages', $data);
			if (isset($res['ok']) && $res['ok']) {
				header('Location: /src/supplier-pages/supplier-messages.php?admin_id=' . urlencode((string)$to));
				exit;
			} else {
				error_log('sb_insert_table messages failed (supplier): ' . json_encode($res));
				$error = 'Failed to save message.';
			}
		}
	}
}
// determine effective current user id for rendering (used to detect which messages are 'me')
$me_effective = $currentUser['id'] ?? null;
if (empty($me_effective) && !empty($_SESSION['gmd_user']['profile']['id'])) $me_effective = $_SESSION['gmd_user']['profile']['id'];
if (empty($me_effective) && !empty($_SESSION['gmd_user']['email'])) {
	$probe = sb_get_table('profiles', 'email=eq.' . rawurlencode($_SESSION['gmd_user']['email']) . '&select=id');
	if (isset($probe['body']) && is_array($probe['body']) && count($probe['body'])>0) $me_effective = $probe['body'][0]['id'] ?? $me_effective;
}

// list admins
$adminsRes = sb_get_table('profiles', 'role=eq.admin&select=id,full_name,email');
$admins = $adminsRes['body'] ?? [];
// normalize admins list and auto-open if there's exactly one admin in the system
$admins = array_values(array_filter($admins, 'is_array'));
if (($admin_id === '' || $admin_id === null) && count($admins) === 1) {
	$single = $admins[0];
	$singleId = $single['id'] ?? '';
	if ($singleId !== '') {
		// redirect so the UI and URL reflect the selected admin
		header('Location: /src/supplier-pages/supplier-messages.php?admin_id=' . urlencode($singleId));
		exit;
	}
}

// load messages between supplier and selected admin (robust fetch + normalization)
$messages = [];
if ($admin_id !== '') {
	$me = $currentUser['id'] ?? '';
	if ($me === '' && !empty($_SESSION['gmd_user']['profile']['id'])) $me = $_SESSION['gmd_user']['profile']['id'];
	if ($me === '' && !empty($_SESSION['gmd_user']['email'])) {
		$probe = sb_get_table('profiles', 'email=eq.' . rawurlencode($_SESSION['gmd_user']['email']) . '&select=id');
		if (isset($probe['body']) && is_array($probe['body']) && count($probe['body'])>0) $me = $probe['body'][0]['id'] ?? $me;
	}
	if ($me !== '') {
		$me_e = rawurlencode((string)$me);
		$adm_e = rawurlencode((string)$admin_id);
		$path = '/rest/v1/messages?or=(and(sender_id.eq.' . $me_e . ',recipient_id.eq.' . $adm_e . '),and(sender_id.eq.' . $adm_e . ',recipient_id.eq.' . $me_e . '))&order=created_at.asc';
		$raw = sb_request('GET', $path, null, false);
		if (isset($raw['body'])) {
			if (is_string($raw['body'])) {
				$dec = json_decode($raw['body'], true);
				if (is_array($dec)) $messages = $dec;
			} elseif (is_array($raw['body'])) {
				$messages = $raw['body'];
			}
		}
		// normalize entries to arrays
		$norm = [];
		if (is_array($messages)) {
			foreach ($messages as $it) {
				if (is_array($it)) $norm[] = $it;
				elseif (is_string($it)) {
					$d = json_decode($it, true);
					if (is_array($d)) $norm[] = $d;
				}
			}
		}
		$messages = $norm;
	}
}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Messages</title>
	<link rel="stylesheet" href="/src/css/styles.css">
	<style>
	.messages-wrap{display:flex;gap:12px}
	.admins{width:280px;background:#fff;padding:12px;border-radius:6px}
	.thread{flex:1;background:#fff;padding:12px;border-radius:6px;display:flex;flex-direction:column;height:70vh}
	.msgs{flex:1;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:10px}
	.msg{max-width:72%;padding:10px 14px;border-radius:18px;line-height:1.3;box-shadow:0 6px 18px rgba(15,23,42,0.04)}
	.msg.me{align-self:flex-end;background:#0078ff;color:#fff;border-bottom-right-radius:6px;border-bottom-left-radius:18px;border-top-left-radius:18px}
	.msg.them{align-self:flex-start;background:#f1f5f9;color:#0f172a;border:1px solid #e6eef6;border-bottom-left-radius:6px;border-bottom-right-radius:18px;border-top-right-radius:18px}
	.msg .meta{font-size:11px;color:rgba(15,23,42,0.5);margin-top:6px}
	.send-box{display:flex;gap:8px;margin-top:8px;align-items:center}
	.send-box input[type="text"]{flex:1;padding:10px;border-radius:20px;border:1px solid #e6eef6}
	.send-box button{padding:8px 12px;border-radius:20px;border:none;background:#0078ff;color:#fff}
	</style>
</head>
<body>
	<?php include __DIR__ . '/supplier-header.php'; ?>
	<div class="app-container">
		<?php include __DIR__ . '/supplier-sidebar.php'; ?>
		<div class="app-main">
			<h2>Messages</h2>
			<div class="messages-wrap">
				<div class="admins card">
					<h4>Admins</h4>
					<input placeholder="Search" oninput="filterAdmins(this.value)" />
					<ul id="adminList">
						<?php foreach($admins as $a): ?>
							<li><a href="?admin_id=<?php echo urlencode($a['id']); ?>"><?php echo htmlspecialchars($a['full_name'] ?? $a['email']); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="thread card">
					<?php if (!$admin_id): ?>
						<p>Select an admin to view the conversation.</p>
					<?php else: ?>
						<div class="msgs" id="msgs">
							<?php foreach($messages as $m):
								$isMe = (string)($m['sender_id'] ?? '') === (string)($me_effective ?? '');
							?>
								<div class="msg <?php echo $isMe? 'me':'them'; ?>">
									<?php if (($m['type'] ?? 'text') === 'text'): ?>
										<div><?php echo nl2br(htmlspecialchars($m['content'])); ?></div>
									<?php elseif (($m['type'] ?? '') === 'image'): ?>
										<div><img src="<?php echo htmlspecialchars($m['content']); ?>" style="max-width:320px;max-height:240px" /></div>
									<?php elseif (($m['type'] ?? '') === 'video'): ?>
										<div><video controls style="max-width:420px"><source src="<?php echo htmlspecialchars($m['content']); ?>" /></video></div>
									<?php else: ?>
										<div><a href="<?php echo htmlspecialchars($m['content']); ?>" target="_blank">Download file</a></div>
									<?php endif; ?>
									<div style="font-size:12px;color:#666;margin-top:6px"><?php echo htmlspecialchars($m['created_at'] ?? ''); ?></div>
								</div>
							<?php endforeach; ?>
						</div>
						<form method="post" id="sendForm" class="send-box">
							<input type="hidden" name="to" value="<?php echo htmlspecialchars($admin_id); ?>" />
							<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
							<input id="contentInput" name="content" type="text" placeholder="Write a message..." autocomplete="off" />
							<button id="sendBtn" type="submit" name="send_message">Send</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<script>
	function filterAdmins(q){
		var items = document.querySelectorAll('#adminList li');
		items.forEach(function(li){
			if(!q) li.style.display='';
			else {
				li.style.display = li.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
			}
		});
	}

	// always scroll messages to bottom (messenger-like)
	function scrollMsgs(){
		var c = document.getElementById('msgs');
		if(!c) return;
		c.scrollTop = c.scrollHeight;
	}
	window.addEventListener('load', function(){ scrollMsgs(); var inp = document.getElementById('contentInput'); if(inp) inp.focus(); });

	// Send form behavior: single-line input, send on Enter, disable when empty, prevent double-click
	document.addEventListener('DOMContentLoaded', function(){
		var form = document.getElementById('sendForm');
		var btn = document.getElementById('sendBtn');
		var inp = document.getElementById('contentInput');
		if(!form || !btn || !inp) return;

		function updateBtn(){ btn.disabled = inp.value.trim().length === 0 }
		inp.addEventListener('input', updateBtn);
		updateBtn();

		// submit on Enter (but allow Shift+Enter if we wanted multiline; keep single-line behavior)
		inp.addEventListener('keydown', function(e){
			if (e.key === 'Enter') { e.preventDefault(); if(!btn.disabled){ btn.click(); } }
		});

		form.addEventListener('submit', function(e){
			if (btn.disabled) { e.preventDefault(); return false; }
			try{ btn.disabled = true; btn.innerText = 'Sending...'; } catch(_){ }
		});
	});
	</script>
</body>
</html>
