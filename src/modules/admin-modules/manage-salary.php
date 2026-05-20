<?php
// Simple Manage Salary module - lists salary history and allows recording a payment
require_once __DIR__ . '/../../config.php';

$employee_id = $_GET['employee_id'] ?? null;

// handle record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
	$token = $_POST['_csrf'] ?? '';
	if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
		echo '<div class="card">Invalid form submission.</div>';
	} else {
		$emp = $_POST['employee_id'] ?? '';
		$date = $_POST['week_start'] ?? date('Y-m-d');
		$days_worked = isset($_POST['selected_days']) ? count((array)$_POST['selected_days']) : (int)($_POST['days_worked'] ?? 0);
		$amount = $_POST['amount'] ?? 0;

		if ($emp && $amount > 0 && $days_worked > 0) {
			$insert = ['employee_id'=>$emp,'amount'=>$amount,'date'=>$date,'days_worked'=>$days_worked];
			sb_insert_table('salary_payments', $insert);
			echo '<div class="card">Payment recorded. (' . htmlspecialchars($days_worked) . ' days — ' . htmlspecialchars($amount) . ')</div>';
		} else {
			echo '<div class="card">Please select an employee and at least one working day.</div>';
		}
	}
}

$paymentsRes = sb_get_table('salary_payments');
$payments = $paymentsRes['body'] ?? [];

// fetch employees to populate selector
$employeesRes = sb_get_table('employees');
$employees = $employeesRes['body'] ?? [];
?>
<div class="card" style="margin-top:12px">
	<h3>Manage Salary</h3>
	<div>
		<form method="post" id="salaryForm">
			<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />

			<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
				<label style="min-width:90px">Employee</label>
				<select name="employee_id" id="employeeSelect" required>
					<option value="">-- Select --</option>
					<?php foreach($employees as $e):
						$id = $e['id'] ?? $e['employee_id'] ?? '';
						$name = trim(($e['name'] ?? ($e['full_name'] ?? 'Employee')));
						$daily = $e['daily_salary'] ?? $e['daily_rate'] ?? $e['salary'] ?? 0;
					?>
					<option value="<?php echo htmlspecialchars($id); ?>" data-daily="<?php echo htmlspecialchars($daily); ?>" <?php echo ($employee_id && $employee_id == $id)?'selected':''; ?>><?php echo htmlspecialchars($name); ?></option>
					<?php endforeach; ?>
				</select>

				<label style="min-width:90px">Week start</label>
				<input type="date" id="weekStart" name="week_start" value="<?php echo date('Y-m-d'); ?>" />
			</div>

			<div style="margin-top:10px">
				<div style="font-weight:600;margin-bottom:6px">Select days worked</div>
				<div id="daysWrap" style="display:flex;gap:8px;flex-wrap:wrap">
					<?php
					// render 7 day checkboxes (Mon-Sun) — default labels; JS will adjust dates relative to week_start
					$dow = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
					for($i=0;$i<7;$i++): ?>
						<label style="display:inline-flex;align-items:center;gap:6px;padding:6px;border:1px solid #ddd;border-radius:6px;cursor:pointer">
							<input type="checkbox" name="selected_days[]" value="<?php echo $i; ?>" class="dayCheckbox" /> <?php echo $dow[$i]; ?>
						</label>
					<?php endfor; ?>
				</div>
			</div>

			<div style="margin-top:10px;display:flex;gap:12px;align-items:center">
				<div><strong>Days:</strong> <span id="daysCount">0</span></div>
				<div><strong>Daily:</strong> ₱<span id="dailyRate">0.00</span></div>
				<div><strong>Total:</strong> ₱<span id="totalAmt">0.00</span></div>
			</div>

			<input type="hidden" name="amount" id="amountInput" value="0" />
			<input type="hidden" name="days_worked" id="daysWorkedInput" value="0" />

			<div style="margin-top:12px">
				<button type="submit" name="record_payment" id="recordBtn">Record Payment</button>
			</div>
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

<script>
(function(){
	var empSel = document.getElementById('employeeSelect');
	var checkboxes = Array.from(document.querySelectorAll('.dayCheckbox'));
	var daysCountEl = document.getElementById('daysCount');
	var dailyEl = document.getElementById('dailyRate');
	var totalEl = document.getElementById('totalAmt');
	var amountInput = document.getElementById('amountInput');
	var daysWorkedInput = document.getElementById('daysWorkedInput');
	var recordBtn = document.getElementById('recordBtn');
	var weekStart = document.getElementById('weekStart');

	function parseFloatSafe(v){ var n = parseFloat(v); return isNaN(n)?0:n; }

	function updateTotals(){
		var selected = checkboxes.filter(function(c){ return c.checked; }).length;
		var daily = parseFloatSafe(empSel.options[empSel.selectedIndex]?.dataset?.daily);
		var total = selected * daily;
		daysCountEl.textContent = selected;
		dailyEl.textContent = daily.toFixed(2);
		totalEl.textContent = total.toFixed(2);
		amountInput.value = total.toFixed(2);
		daysWorkedInput.value = selected;
		recordBtn.disabled = (selected === 0 || !empSel.value || total <= 0);
	}

	empSel && empSel.addEventListener('change', updateTotals);
	checkboxes.forEach(function(ch){ ch.addEventListener('change', updateTotals); });
	weekStart && weekStart.addEventListener('change', function(){
		// optionally update labels to show dates next to day names in future
	});

	// initial compute
	updateTotals();

	// prevent double submits
	var form = document.getElementById('salaryForm');
	form && form.addEventListener('submit', function(){ recordBtn.disabled = true; });
})();
</script>
