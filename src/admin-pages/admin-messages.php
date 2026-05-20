<?php
require_once __DIR__ . '/../config.php';
require_login();

$currentUser = get_current_user();
$supplier_id = $_GET['supplier_id'] ?? null;
// Quick check: ensure Supabase is configured for server operations
if (defined('SUPABASE_URL') && SUPABASE_URL === '') {
	$error = 'SUPABASE_URL is not configured. Server cannot save messages.';
}
if (function_exists('get_supabase_key') && !get_supabase_key(false)) {
	$error = ($error ?? '') . ' SUPABASE_KEY (service role) is not configured.';
}
// handle sending a message (text or file)
// Accept posts triggered by pressing Enter (no submit-button value), by checking content or file presence too.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (
	isset($_POST['send_message']) || isset($_POST['send_file']) ||
	(!empty(trim($_POST['content'] ?? '')) ) ||
	(!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK)
)) {

	error_log('admin-messages: POST received keys: ' . json_encode(array_keys($_POST)) . ' hasFiles=' . (isset($_FILES) ? '1' : '0'));
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		// Log debugging info to help diagnose why CSRF verification fails
		$log = [
			'msg' => 'CSRF verification failed on admin-messages POST',
			'session_id' => session_id(),
			'session_cookie' => $_COOKIE[session_name()] ?? null,
			'session_csrf_token' => isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'],0,16) : null,
			'posted_token' => $token ? substr($token,0,16) : null,
			'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
			'request_uri' => $_SERVER['REQUEST_URI'] ?? null
		];
		error_log(json_encode($log));
		$error = 'Invalid form submission.';
	} else {
		error_log('admin-messages: CSRF verified for session=' . session_id());
		$to = $_POST['to'] ?? '';
	$content = trim($_POST['content'] ?? '');
	$msgType = 'text';
	// handle file upload
	if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
		$f = $_FILES['attachment'];
		$tmp = $f['tmp_name'];
		$name = basename($f['name']);
		$mime = mime_content_type($tmp) ?: 'application/octet-stream';
		$ext = pathinfo($name, PATHINFO_EXTENSION);
		$objectPath = 'messages/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
		$up = sb_upload_file('messages', $objectPath, $tmp, $mime);
		// handle the richer upload response (ok + object_path/url)
		if (isset($up['error']) || (isset($up['ok']) && !$up['ok'])) {
			error_log('sb_upload_file error: ' . json_encode($up));
			$uploadErr = $up['error'] ?? ('status:' . ($up['status'] ?? 'unknown'));
			$raw = isset($up['raw']) ? ' ' . htmlspecialchars(substr($up['raw'],0,500)) : '';
			$error = 'Upload failed: ' . htmlspecialchars((string)$uploadErr) . $raw;
		} elseif (isset($up['url'])) {
			$content = $up['url'];
			if (strpos($mime, 'image/') === 0) $msgType = 'image';
			elseif (strpos($mime, 'video/') === 0) $msgType = 'video';
			else $msgType = 'file';
		} elseif (isset($up['object_path'])) {
			// storage is private; try to get signed URL
			error_log('sb_upload_file returned object_path: ' . json_encode($up));
			$signed = sb_get_signed_url($up['bucket'] ?? 'messages', $up['object_path']);
			if (isset($signed['url'])) {
				$content = $signed['url'];
				if (strpos($mime, 'image/') === 0) $msgType = 'image';
				elseif (strpos($mime, 'video/') === 0) $msgType = 'video';
				else $msgType = 'file';
			} else {
				error_log('sb_get_signed_url failed: ' . json_encode($signed));
				$signErr = $signed['error'] ?? ('status:' . ($signed['status'] ?? 'unknown'));
				$raw = isset($signed['raw']) ? ' ' . htmlspecialchars(substr(json_encode($signed['raw']),0,500)) : '';
				$error = 'Upload succeeded but failed to generate signed URL: ' . htmlspecialchars((string)$signErr) . $raw;
			}
		} else {
			error_log('sb_upload_file returned unexpected response: ' . json_encode($up));
			$error = 'Upload failed: unexpected response from storage.' . (isset($up['raw']) ? ' ' . htmlspecialchars(substr($up['raw'],0,500)) : '');
		}
	}

		if ($to && $content) {
			// determine sender_id reliably from session or profiles table
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
			if (empty($sender_id)) {
				error_log('admin-messages: could not determine sender_id for session user: ' . json_encode($_SESSION['gmd_user'] ?? null));
			} else {
				error_log('admin-messages: resolved sender_id=' . json_encode($sender_id));
			}

			$data = [
				'sender_id' => $sender_id,
				'recipient_id' => $to,
				'content' => $content,
				'type' => $msgType,
				'status' => 'sent'
			];
			// Prevent accidental duplicate inserts when the user double-clicks the Send button:
			// Fetch the last message from this sender -> recipient and if the content matches
			// and it was sent within the last 10 seconds, treat it as a duplicate and skip inserting.
			$skipInsert = false;
			if (!empty($sender_id)) {
				$lastRes = sb_get_table('messages', 'sender_id=eq.' . rawurlencode((string)$sender_id) . '&recipient_id=eq.' . rawurlencode((string)$to) . '&select=content,created_at&order=created_at.desc&limit=1');
				if (isset($lastRes['body']) && is_array($lastRes['body']) && count($lastRes['body'])>0) {
					$last = $lastRes['body'][0];
					$lastContent = $last['content'] ?? null;
					$lastTime = isset($last['created_at']) ? strtotime($last['created_at']) : false;
					if ($lastContent !== null && $lastContent === $content && $lastTime !== false && (time() - $lastTime) < 10) {
						$skipInsert = true;
						error_log('admin-messages: skipped duplicate insert for sender=' . json_encode($sender_id) . ' recipient=' . json_encode($to));
					}
				}
			}
			if ($skipInsert) {
				header('Location: /src/admin-pages/admin-messages.php?supplier_id=' . urlencode((string)$to));
				exit;
			}
			error_log('admin-messages: inserting message: ' . json_encode($data));
			$res = sb_insert_table('messages', $data);
		if (isset($res['ok']) && $res['ok']) {
			error_log('admin-messages: insert ok, response=' . json_encode($res));
			header('Location: /src/admin-pages/admin-messages.php?supplier_id=' . urlencode((string)$to));
			exit;
		} else {
			$error = 'Failed to save message.';
			// include brief details for debugging
			$raw = isset($res['raw']) ? $res['raw'] : (isset($res['body']) ? json_encode($res['body']) : '');
			error_log('sb_insert_table messages failed: ' . json_encode($res));
			if ($raw) $error .= ' ' . htmlspecialchars(substr($raw,0,800));
		}
	}
	}
}

// list suppliers (profiles with role = supplier)
$suppliersRes = sb_get_table('profiles', 'role=eq.supplier&select=id,email,full_name');
$suppliers = $suppliersRes['body'] ?? [];

// If supplier_id wasn't provided via GET, try to derive it from POST ('to') or default to first supplier
if (empty($supplier_id)) {
	if (!empty($_POST['to'])) {
		$supplier_id = $_POST['to'];
		error_log('admin-messages: using supplier_id from POST->to: ' . json_encode($supplier_id));
	} elseif (is_array($suppliers) && count($suppliers) > 0) {
		$first = $suppliers[0]['id'] ?? null;
		if (!empty($first)) {
			$supplier_id = $first;
			error_log('admin-messages: defaulting supplier_id to first supplier: ' . json_encode($supplier_id));
			// optionally redirect so URL shows supplier_id
			if (empty($_GET['supplier_id'])) {
				header('Location: /src/admin-pages/admin-messages.php?supplier_id=' . urlencode((string)$supplier_id));
				exit;
			}
		}
	}
}

// load messages for supplier thread
$messages = [];
// diagnostic info: log session and supplier state to help debug missing ids
error_log('admin-messages: session_gmd_user=' . json_encode($_SESSION['gmd_user'] ?? null) . ' currentUser=' . json_encode($currentUser));
error_log('admin-messages: supplier_id(GET)=' . json_encode($_GET['supplier_id'] ?? null) . ' supplier_id(var)=' . json_encode($supplier_id) . ' suppliers_count=' . (is_array($suppliers)?count($suppliers):0));

if ($supplier_id) {
	// determine effective current user id (me)
	$me = $currentUser['id'] ?? null;
	if (empty($me) && !empty($_SESSION['gmd_user']['profile']['id'])) {
		$me = $_SESSION['gmd_user']['profile']['id'];
	}
	if (empty($me) && !empty($_SESSION['gmd_user']['email'])) {
		// try to lookup profile by email
		$probe = sb_get_table('profiles', 'email=eq.' . rawurlencode($_SESSION['gmd_user']['email']) . '&select=id');
		if (isset($probe['body']) && is_array($probe['body']) && count($probe['body'])>0) {
			$me = $probe['body'][0]['id'] ?? $me;
			error_log('admin-messages: resolved me via email lookup: ' . json_encode($me));
		}
	}

	if (empty($me) || empty($supplier_id)) {
		error_log('admin-messages: missing user or supplier id for message fetch after resolution: me=' . json_encode($me) . ' supplier=' . json_encode($supplier_id));
		$error = 'Cannot load messages: missing user or supplier id.';
		$messages = [];
	} else {
		$me_e = rawurlencode((string)$me);
		$sup_e = rawurlencode((string)$supplier_id);

		// fetch messages where (sender=me and recipient=supplier) OR (sender=supplier and recipient=me)
		$path = '/rest/v1/messages?or=(and(sender_id.eq.' . $me_e . ',recipient_id.eq.' . $sup_e . '),and(sender_id.eq.' . $sup_e . ',recipient_id.eq.' . $me_e . '))&order=created_at.asc';
		$raw = sb_request('GET', $path, null, false);
		if (!isset($raw['ok']) || !$raw['ok']) {
			// log and show brief error to user
			error_log('sb_request GET messages failed: ' . json_encode($raw));
			$errBody = $raw['raw'] ?? json_encode($raw['body'] ?? '');
			$error = 'Failed to load messages.' . ($errBody ? ' ' . htmlspecialchars(substr($errBody,0,800)) : '');
		} else {
			if (isset($raw['body'])) {
				if (is_string($raw['body'])) {
					$dec = json_decode($raw['body'], true);
					if (is_array($dec)) $messages = $dec;
				} elseif (is_array($raw['body'])) {
					$messages = $raw['body'];
				}
			}
		}
	}

	// normalize messages to ensure each entry is an array with keys
	if (!is_array($messages)) $messages = [];
	else {
		$norm = [];
		foreach ($messages as $it) {
			if (is_array($it)) {
				$norm[] = $it;
			} elseif (is_string($it)) {
				$d = json_decode($it, true);
				if (is_array($d)) $norm[] = $d;
			}
			// ignore other types
		}
		$messages = $norm;
	}

// determine effective current user id for rendering (used to detect which messages are 'me')
$me_effective = $currentUser['id'] ?? null;
if (empty($me_effective) && !empty($_SESSION['gmd_user']['profile']['id'])) $me_effective = $_SESSION['gmd_user']['profile']['id'];
if (empty($me_effective) && !empty($_SESSION['gmd_user']['email'])) {
    $probe = sb_get_table('profiles', 'email=eq.' . rawurlencode($_SESSION['gmd_user']['email']) . '&select=id');
    if (isset($probe['body']) && is_array($probe['body']) && count($probe['body'])>0) $me_effective = $probe['body'][0]['id'] ?? $me_effective;
}

}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Admin Messages</title>
	<link rel="stylesheet" href="/src/css/styles.css">
	<style>
	.messages-wrap{display:flex;gap:12px}
	.suppliers{width:280px;background:#fff;padding:12px;border-radius:6px}
	.suppliers input{width:100%;padding:6px;margin-bottom:8px}
	.suppliers ul{list-style:none;padding:0;margin:0}
	.suppliers li{margin-bottom:6px}
	.thread{flex:1;background:#f6f7fb;padding:12px;border-radius:6px;display:flex;flex-direction:column;height:70vh}
	.msgs{flex:1;overflow:auto;padding:16px;display:flex;flex-direction:column;gap:12px}
	.msg-row{display:flex;flex-direction:column;max-width:80%;}
	.msg-row.me-row{align-items:flex-end}
	.msg-row.them-row{align-items:flex-start}
	.msg{padding:10px 14px;border-radius:18px;line-height:1.3;box-shadow:0 6px 18px rgba(15,23,42,0.06);}
	.msg.me{align-self:flex-end;background:#0078ff;color:#fff;border-bottom-right-radius:6px;border-bottom-left-radius:18px;border-top-left-radius:18px}
	.msg.them{align-self:flex-start;background:#fff;color:#222;border:1px solid #e6e6e6;border-bottom-left-radius:6px;border-bottom-right-radius:18px;border-top-right-radius:18px}
	.msg .meta{font-size:12px;color:#666;margin-top:6px;opacity:0.9}
	.msg.me .meta{color:rgba(255,255,255,0.85);text-align:right}
	.msg.them .meta{text-align:left}
	.send-box{display:flex;gap:8px;margin-top:8px;padding-top:8px;border-top:1px solid #eaeaea}
	.send-box textarea{flex:1;padding:10px;border-radius:20px;border:1px solid #ddd}
	.send-box input[type=file]{align-self:center}
	.send-btn{background:#0078ff;color:#fff;border:none;padding:8px 12px;border-radius:20px}
	</style>
</head>
<body>
	<?php include __DIR__ . '/header.php'; ?>
	<div class="app-container">
		<?php include __DIR__ . '/sidebar.php'; ?>
		<div class="app-main">
			<h2>Messages</h2>
			<div class="messages-wrap">
				<div class="suppliers card">
					<h4>Suppliers</h4>
					<input placeholder="Search" oninput="filterSuppliers(this.value)" />
					<ul id="supplierList">
						<?php foreach($suppliers as $s): ?>
							<li><a href="?supplier_id=<?php echo urlencode((string)($s['id'] ?? '')); ?>"><?php echo htmlspecialchars($s['full_name'] ?? $s['email']); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="thread card">
					<?php if (!empty($error)): ?>
						<div style="padding:8px;background:#ffecec;border:1px solid #f5c2c2;color:#8a1f1f;margin-bottom:8px;border-radius:6px"><?php echo htmlspecialchars($error); ?></div>
					<?php endif; ?>
					<?php if (!$supplier_id): ?>
						<p>Select a supplier to view the conversation.</p>
					<?php else: ?>
						<div class="msgs" id="msgs">
							<?php foreach($messages as $m):
								$isMe = ($m['sender_id'] == ($me_effective ?? null));
								$created = $m['created_at'] ?? null;
								$ts = '';
								if ($created) {
									$time = strtotime($created);
									if ($time !== false) {
										$now = time();
										// if message is from today, show only time (e.g. "9:40 am")
										if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
											$ts = date('g:i a', $time);
										} elseif ($now - $time < 7 * 86400) {
											// within last week -> show relative days (e.g. "2d ago")
											$days = floor(($now - $time) / 86400);
											$ts = $days . 'd ago';
										} else {
											// older -> full date
											$ts = date('g:i a F j, Y', $time);
										}
									}
								}
							?>
								<div class="msg-row <?php echo $isMe? 'me-row':'them-row'; ?>">
									<div class="msg <?php echo $isMe? 'me':'them'; ?>">
										<?php if (($m['type'] ?? 'text') === 'text'): ?>
											<div><?php echo nl2br(htmlspecialchars($m['content'])); ?></div>
										<?php elseif (($m['type'] ?? '') === 'image'): ?>
											<div><img src="<?php echo htmlspecialchars($m['content']); ?>" style="max-width:320px;max-height:240px;border-radius:8px" /></div>
										<?php elseif (($m['type'] ?? '') === 'video'): ?>
											<div><video controls style="max-width:420px;border-radius:8px"><source src="<?php echo htmlspecialchars($m['content']); ?>" /></video></div>
										<?php else: ?>
											<div><a href="<?php echo htmlspecialchars($m['content']); ?>" target="_blank">Download file</a></div>
										<?php endif; ?>
										<?php if ($ts): ?>
											<div class="meta"><?php echo htmlspecialchars($ts); ?></div>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<form id="sendForm" method="post" class="send-box">
							<input type="hidden" name="to" value="<?php echo htmlspecialchars($supplier_id); ?>" />
							<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
							<input id="msgContent" name="content" type="text" placeholder="Write a message..." style="flex:1;padding:10px;border-radius:20px;border:1px solid #ddd" autocomplete="off" />
							<button id="sendBtn" type="submit" name="send_message" class="send-btn" disabled>Send</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<script>
	// scroll messages to bottom and focus
	function scrollMsgs(){
		var c = document.getElementById('msgs');
		if(c) c.scrollTop = c.scrollHeight;
	}
	window.addEventListener('load', function(){ scrollMsgs(); var ta = document.getElementById('msgContent'); if(ta) ta.focus(); });
	function filterSuppliers(q){
		var items = document.querySelectorAll('#supplierList li');
		items.forEach(function(li){
			if(!q) li.style.display='';
			else {
				li.style.display = li.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
			}
		});
	}

// Prevent duplicate sends by disabling the send button and inputs on submit
document.addEventListener('DOMContentLoaded', function(){
	var form = document.getElementById('sendForm');
	var btn = document.getElementById('sendBtn');
	var ta = document.getElementById('msgContent');
	if(!form || !btn || !ta) return;
	// enable/disable send button based on non-empty trimmed input
	function updateBtn(){
		try{
			btn.disabled = (ta.value.trim().length === 0);
		}catch(e){}
	}
	ta.addEventListener('input', updateBtn);
	// initialize
	updateBtn();
	form.addEventListener('submit', function(e){
		if(btn.disabled) { e.preventDefault(); return false; }
		// disable only the send button to avoid duplicate clicks; DO NOT disable the input
		try { btn.disabled = true; btn.innerText = 'Sending...'; } catch(_){ }
	});
});
	</script>
</body>
</html>
