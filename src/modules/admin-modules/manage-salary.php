<?php
// Simple Manage Salary module - lists salary history and allows recording a payment
require_once __DIR__ . '/../../config.php';

$employee_id = $_GET['employee_id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		echo '<div class="card">Invalid form submission.</div>';
	} else {
		$emp = $_POST['employee_id'] ?? '';
		$amount = $_POST['amount'] ?? 0;
		$date = $_POST['date'] ?? date('Y-m-d');
		if ($emp && $amount) {
			sb_insert_table('salary_payments', ['employee_id'=>$emp,'amount'=>$amount,'date'=>$date]);
			echo '<div class="card">Payment recorded.</div>';
		}
	}
}

$paymentsRes = sb_get_table('salary_payments');
$payments = $paymentsRes['body'] ?? [];
?>
<div class="card" style="margin-top:12px">
	<h3>Manage Salary</h3>
	<div>
		<form method="post" style="display:flex;gap:8px;align-items:center">
			<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
			<label>Employee ID</label>
			<input name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>" />
			<label>Amount</label>
			<input name="amount" type="number" step="0.01" />
			<label>Date</label>
			<input name="date" type="date" value="<?php echo date('Y-m-d'); ?>" />
			<button type="submit" name="record_payment">Record Payment</button>
		</form>
	</div>
	<h4 style="margin-top:12px">Recent Payments</h4>
	<?php if (empty($payments)): ?>
		<p>No payments recorded yet.</p>
	<?php else: ?>
		<table border="0" cellpadding="6">
			<thead><tr><th>Employee</th><th>Amount</th><th>Date</th></tr></thead>
			<tbody>
			<?php foreach($payments as $p): ?>
				<tr>
					<td><?php echo htmlspecialchars($p['employee_id'] ?? ''); ?></td>
					<td><?php echo htmlspecialchars($p['amount'] ?? ''); ?></td>
					<td><?php echo htmlspecialchars($p['date'] ?? ''); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
