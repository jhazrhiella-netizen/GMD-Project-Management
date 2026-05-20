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
			<h4>Employees</h4>
			<button id="openAddEmployee" class="btn">Add Employee</button>

			<!-- Modal -->
			<style>
			.employee-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:1200}
			.employee-modal{background:#fff;border-radius:8px;padding:18px;max-width:420px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,0.2)}
			.employee-modal h4{margin-top:0}
			.employee-modal .row{display:flex;flex-direction:column;gap:8px;margin-bottom:10px}
			.employee-modal .actions{text-align:right}
			.employee-modal input[type="text"], .employee-modal input[type="email"]{padding:8px;border:1px solid #ddd;border-radius:6px;width:100%}
			.employee-modal .close-btn{background:transparent;border:none;font-size:18px;position:absolute;right:12px;top:8px}
			</style>
			<div id="addEmployeeModal" class="employee-modal-overlay" aria-hidden="true">
				<div class="employee-modal" role="dialog" aria-modal="true" aria-labelledby="addEmployeeTitle">
					<button type="button" class="close-btn" id="closeAddEmployee">×</button>
					<h4 id="addEmployeeTitle">Add Employee</h4>
					<form id="addEmployeeForm" method="post">
						<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
						<div class="row"><label>Name</label><input id="emp_name" name="name" type="text" required /></div>
						<div class="row"><label>Position</label><input id="emp_position" name="position" type="text" /></div>
						<div class="row"><label>Email</label><input id="emp_email" name="email" type="email" /></div>
						<div class="row"><label>Phone</label><input id="emp_phone" name="phone" type="text" /></div>
						<div class="actions"><button type="submit" name="add_employee">Add</button></div>
					</form>
				</div>
			</div>

			<script>
			(function(){
				var open = document.getElementById('openAddEmployee');
				var modal = document.getElementById('addEmployeeModal');
				var close = document.getElementById('closeAddEmployee');
				var form = document.getElementById('addEmployeeForm');
				open && open.addEventListener('click', function(){
					if(modal){ modal.style.display='flex'; modal.setAttribute('aria-hidden','false');
						var f = document.getElementById('emp_name'); if(f) f.focus(); }
				});
				close && close.addEventListener('click', function(){ if(modal){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); form.reset(); } });
				// click outside to close
				modal && modal.addEventListener('click', function(e){ if(e.target === modal){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); form.reset(); } });
				// ESC to close
				document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal && modal.style.display === 'flex'){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); form.reset(); } });
			})();
			</script>
		</div>
	</div>
</div>
