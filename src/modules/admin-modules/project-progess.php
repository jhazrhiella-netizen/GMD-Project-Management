<?php
// Project progress module
require_once __DIR__ . '/../../config.php';
$project_id = $_GET['id'] ?? null;
if (!$project_id) return;

// update progress via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		echo '<div class="card">Invalid form submission.</div>';
	} else {
		$progress = intval($_POST['progress'] ?? 0);
		sb_update_table('projects', ['progress'=>$progress], 'id=eq.' . urlencode($project_id));
		echo '<div class="card">Progress updated.</div>';
	}
}

$projRes = sb_get_table('projects', 'id=eq.' . urlencode($project_id));
$project = $projRes['body'][0] ?? null;
$current = intval($project['progress'] ?? 0);
?>
<div class="card" style="margin-top:12px">
	<h3>Project Progress</h3>
	<?php if (!$project): ?>
		<p>Project not found.</p>
	<?php else: ?>
		<p><strong><?php echo htmlspecialchars($project['name'] ?? ''); ?></strong></p>
		<div style="background:#eee;border-radius:6px;overflow:hidden;height:20px;margin-bottom:8px">
			<div style="width:<?php echo $current; ?>%;background:#0b5ed7;height:100%"></div>
		</div>
		<form method="post">
			<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
			<label>Set Progress (%)</label>
			<input type="range" name="progress" min="0" max="100" value="<?php echo $current; ?>" oninput="this.nextElementSibling.value=this.value" /> <output><?php echo $current; ?></output>
			<button type="submit" name="update_progress">Update</button>
		</form>
	<?php endif; ?>
</div>
