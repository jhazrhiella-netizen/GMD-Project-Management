<?php
// Employee list module
// handle add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		// ignore invalid submissions
	} else {
		$name = $_POST['name'] ?? '';
		$position = $_POST['position'] ?? '';
		$phone = $_POST['phone'] ?? '';
		$email = $_POST['email'] ?? '';
		if ($name) {
			$data = ['name'=>$name,'position'=>$position,'phone'=>$phone,'email'=>$email];
			sb_insert_table('employees', $data);
			header('Location: ' . $_SERVER['REQUEST_URI']);
			exit;
		}
	}
}

$res = sb_get_table('employees');
$employees = $res['body'] ?? [];
?>
<div class="card">
	<h3>Employees</h3>
	<div style="display:flex;gap:12px">
		<div style="flex:1">
			<?php if (empty($employees)): ?>
				<p>No employees found.</p>
			<?php else: ?>
				<table border="0" cellpadding="6">
					<thead><tr><th>Name</th><th>Position</th><th>Email</th><th>Phone</th></tr></thead>
					<tbody>
					<?php foreach($employees as $e): ?>
						<tr>
							<td><?php echo htmlspecialchars($e['name'] ?? ''); ?></td>
							<td><?php echo htmlspecialchars($e['position'] ?? ''); ?></td>
							<td><?php echo htmlspecialchars($e['email'] ?? ''); ?></td>
							<td><?php echo htmlspecialchars($e['phone'] ?? ''); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<div style="width:320px">
			<h4>Add Employee</h4>
			<form method="post">
				<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
				<label>Name</label>
				<input name="name" required />
				<label>Position</label>
				<input name="position" />
				<label>Email</label>
				<input name="email" type="email" />
				<label>Phone</label>
				<input name="phone" />
				<button type="submit" name="add_employee">Add</button>
			</form>
		</div>
	</div>
</div>
