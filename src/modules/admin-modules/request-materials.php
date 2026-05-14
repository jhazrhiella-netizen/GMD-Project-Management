<?php
// Material request module (simple)
require_once __DIR__ . '/../../config.php';
$project_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_materials'])) {
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		echo '<div class="card">Invalid form submission.</div>';
	} else {
		$project = $_POST['project_id'] ?? $project_id;
	$material = $_POST['material'] ?? '';
	$quantity = $_POST['quantity'] ?? 0;
	$supplier = $_POST['supplier'] ?? null;
		if ($project && $material) {
		$data = ['project_id'=>$project,'material'=>$material,'quantity'=>$quantity,'supplier_id'=>$supplier,'status'=>'requested'];
		sb_insert_table('material_requests', $data);
		echo '<div class="card">Material request submitted.</div>';
	}
	}
}

$materialsRes = sb_get_table('materials');
$materials = $materialsRes['body'] ?? [];
$suppliersRes = sb_get_table('profiles', 'role=eq.supplier&select=id,full_name,email');
$suppliers = $suppliersRes['body'] ?? [];
?>
<div class="card" style="margin-top:12px">
	<h3>Request Materials</h3>
	<form method="post">
		<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
		<input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>" />
		<label>Material</label>
		<select name="material">
			<?php foreach($materials as $m): ?>
				<option value="<?php echo htmlspecialchars($m['name'] ?? ''); ?>"><?php echo htmlspecialchars($m['name'] ?? ''); ?></option>
			<?php endforeach; ?>
			<option value="Other">Other</option>
		</select>
		<label>Quantity</label>
		<input name="quantity" type="number" value="1" />
		<label>Supplier</label>
		<select name="supplier">
			<option value="">--select--</option>
			<?php foreach($suppliers as $s): ?>
				<option value="<?php echo htmlspecialchars($s['id']); ?>"><?php echo htmlspecialchars($s['full_name'] ?? $s['email']); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" name="request_materials">Request</button>
	</form>
</div>
