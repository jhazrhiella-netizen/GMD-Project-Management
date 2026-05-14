<?php
require_once __DIR__ . '/../config.php';
require_login();

$currentUser = get_current_user();
$supplier_id = $_GET['supplier_id'] ?? null;
// handle sending a message (text or file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['send_message']) || isset($_POST['send_file']))) {
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		$error = 'Invalid form submission.';
	} else {
		$to = $_POST['to'] ?? '';
	$content = $_POST['content'] ?? '';
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
		if (isset($up['url'])) {
			$content = $up['url'];
			if (strpos($mime, 'image/') === 0) $msgType = 'image';
			elseif (strpos($mime, 'video/') === 0) $msgType = 'video';
			else $msgType = 'file';
		} elseif (isset($up['object_path'])) {
			$signed = sb_get_signed_url($up['bucket'] ?? 'messages', $up['object_path']);
			if (isset($signed['url'])) {
				$content = $signed['url'];
				if (strpos($mime, 'image/') === 0) $msgType = 'image';
				elseif (strpos($mime, 'video/') === 0) $msgType = 'video';
				else $msgType = 'file';
			} else {
				$error = 'Upload succeeded but failed to generate signed URL.';
			}
		} else {
			$error = 'Upload failed';
		}
	}

		if ($to && $content) {
		$data = [
			'sender_id' => $currentUser['id'] ?? null,
				'recipient_id' => $to,
			'content' => $content,
			'type' => $msgType,
			'status' => 'sent'
		];
		sb_insert_table('messages', $data);
		header('Location: /src/admin-pages/admin-messages.php?supplier_id=' . urlencode($to));
		exit;
	}
	}
}

// list suppliers (profiles with role = supplier)
$suppliersRes = sb_get_table('profiles', 'role=eq.supplier&select=id,email,full_name');
$suppliers = $suppliersRes['body'] ?? [];

// load messages for supplier thread
$messages = [];
if ($supplier_id) {
	$me = $currentUser['id'] ?? null;
	$q = "(sender_id.eq." . urlencode($me) . ",recipient_id.eq." . urlencode($me) . ")";
	// simpler: fetch messages where (sender=me and recipient=supplier) OR (sender=supplier and recipient=me)
	$raw = sb_request('GET', '/rest/v1/messages?or=(and(sender_id.eq.' . urlencode($me) . ',recipient_id.eq.' . urlencode($supplier_id) . '),and(sender_id.eq.' . urlencode($supplier_id) . ',recipient_id.eq.' . urlencode($me) . '))&order=created_at.asc', null, false);
	if (isset($raw['body']) && is_array($raw['body'])) $messages = $raw['body'];
}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Admin Messages</title>
	<link rel="stylesheet" href="/src/css/styles.css">
	<style>.messages-wrap{display:flex;gap:12px}.suppliers{width:280px;background:#fff;padding:12px;border-radius:6px}.thread{flex:1;background:#fff;padding:12px;border-radius:6px;display:flex;flex-direction:column;height:70vh}.msgs{flex:1;overflow:auto;padding:8px}.msg{margin-bottom:8px;padding:8px;border-radius:6px}.msg.me{background:#e6f0ff;text-align:right}.msg.them{background:#f1f1f1;text-align:left}.send-box{display:flex;gap:8px;margin-top:8px}
	.send-box textarea{flex:1;padding:8px}
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
							<li><a href="?supplier_id=<?php echo urlencode($s['id']); ?>"><?php echo htmlspecialchars($s['full_name'] ?? $s['email']); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="thread card">
					<?php if (!$supplier_id): ?>
						<p>Select a supplier to view the conversation.</p>
					<?php else: ?>
						<div class="msgs">
							<?php foreach($messages as $m):
								$isMe = $m['sender_id'] == ($currentUser['id'] ?? null);
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
						<form method="post" class="send-box" enctype="multipart/form-data">
							<input type="hidden" name="to" value="<?php echo htmlspecialchars($supplier_id); ?>" />
							<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
							<textarea name="content" rows="2"></textarea>
							<input type="file" name="attachment" accept="image/*,video/*" />
							<button type="submit" name="send_message">Send</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<script>
	function filterSuppliers(q){
		var items = document.querySelectorAll('#supplierList li');
		items.forEach(function(li){
			if(!q) li.style.display='';
			else {
				li.style.display = li.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
			}
		});
	}
	</script>
</body>
</html>
