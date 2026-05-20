<?php
require_once __DIR__ . '/../../config.php';
require_login();

$flash = '';
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

// Handle save quantity and delete for project materials (single + bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        $flash = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = intval($_POST['id'] ?? 0);
            $quantity = $_POST['quantity'] ?? '';
            if (!$id || !is_numeric($quantity)) {
                $flash = 'Invalid quantity.';
            } else {
                $q = floatval($quantity);
                if ($q < 0) $q = 0; // prevent negative quantities
                sb_update_table('project_materials', ['quantity' => $q], 'id=eq.' . $id);
                $flash = 'Quantity updated.';
            }
        } elseif ($action === 'save_all') {
            $quantities = $_POST['quantities'] ?? [];
            $updated = 0; $errors = [];
            if (!is_array($quantities)) $quantities = [];
            foreach ($quantities as $pmid => $qval) {
                $pmid = intval($pmid);
                if (!$pmid) continue;
                if (!is_numeric($qval)) { $errors[] = $pmid; continue; }
                $q = floatval($qval);
                if ($q < 0) $q = 0; // coerce negatives to zero
                $res = sb_update_table('project_materials', ['quantity' => $q], 'id=eq.' . $pmid);
                if (isset($res['ok']) && $res['ok']) $updated++; else $errors[] = $pmid;
            }
            $flash = $updated . ' quantities updated.' . (!empty($errors) ? ' Some failed.' : '');
        } elseif ($action === 'delete' || isset($_POST['delete_id'])) {
            $id = intval($_POST['id'] ?? ($_POST['delete_id'] ?? 0));
            if ($id) {
                sb_delete_table('project_materials', 'id=eq.' . $id);
                $flash = 'Material removed from project.';
            }
        }
    }
}

$rows = [];
if ($project_id) {
    $pmRes = sb_get_table('project_materials', 'project_id=eq.' . urlencode($project_id));
    if (isset($pmRes['body']) && is_array($pmRes['body'])) $rows = $pmRes['body'];
}

// group rows by group_id for display
$groups = [];
if (!empty($rows) && is_array($rows)) {
    foreach ($rows as $pm) {
        $gid = isset($pm['group_id']) ? intval($pm['group_id']) : 1;
        if (!isset($groups[$gid])) $groups[$gid] = [];
        $groups[$gid][] = $pm;
    }
    ksort($groups, SORT_NUMERIC);
}

// Fetch suppliers for send-request form
$suppliersRes = sb_get_table('profiles', 'role=eq.supplier&select=id,full_name,email');
$suppliers = isset($suppliersRes['body']) && is_array($suppliersRes['body']) ? $suppliersRes['body'] : [];

// Handle send request action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_request') {
    $token = $_POST['_csrf'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        $flash = 'Invalid form submission.';
    } else {
        $supplier_id = isset($_POST['supplier_id']) ? trim((string)$_POST['supplier_id']) : '';
        if (!$project_id) {
            $flash = 'No project selected.';
        } elseif (!$supplier_id) {
            $flash = 'Select a supplier to send the request to.';
        } else {
            $current = get_current_user();
            $requested_by = $current['id'] ?? null;
            $sent = 0; $errors = [];
            // determine optional group filter
            $group_id = isset($_POST['group_id']) && ($_POST['group_id'] !== '') ? intval($_POST['group_id']) : null;
            // reload project materials fresh, optionally filtering by group
            $filter = 'project_id=eq.' . urlencode($project_id);
            if ($group_id) $filter .= '&group_id=eq.' . urlencode($group_id);
            $pmRes2 = sb_get_table('project_materials', $filter);
            $pmList = isset($pmRes2['body']) && is_array($pmRes2['body']) ? $pmRes2['body'] : [];
            foreach ($pmList as $pm) {
                $mid = $pm['material_id'] ?? null;
                $qty = floatval($pm['quantity'] ?? 0);
                if (!$mid) continue;
                if ($qty <= 0) continue; // skip items with zero quantity
                // look up material name
                $mRes = sb_get_table('materials', 'id=eq.' . urlencode($mid));
                $m = isset($mRes['body'][0]) ? $mRes['body'][0] : null;
                $mname = $m['name'] ?? ('#' . $mid);
                $reqData = [
                    'project_id' => $project_id,
                    'material' => $mname,
                    'quantity' => $qty,
                    'supplier_id' => $supplier_id,
                    'status' => 'requested',
                    'requested_by' => $requested_by
                ];
                $res = sb_insert_table('material_requests', $reqData);
                if (isset($res['ok']) && $res['ok']) {
                    $sent++;
                } else {
                    $errors[] = ['material_id'=>$mid,'material'=>$mname,'res'=>$res];
                    error_log('material_requests insert failed: ' . json_encode(['material_id'=>$mid,'res'=>$res]));
                }
            }
            $flash = $sent . ' request(s) created.' . ($group_id ? ' (group ' . intval($group_id) . ')' : '');
            if (!empty($errors)) {
                $flash .= ' Some failed.';
                // include brief error details for debugging
                $flash .= ' Details: ' . htmlspecialchars(json_encode(array_map(function($e){ return ['id'=>$e['material_id'],'name'=>$e['material'],'status'=>($e['res']['status']??null),'error'=>($e['res']['body']??$e['res']['error']??$e['res']['raw']??null)]; }, $errors)));
            }
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Materials</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <style>
    .materials-table { width:100%; border-collapse:collapse }
    .materials-table th, .materials-table td { border:1px solid #ddd; padding:8px }
    .materials-actions form { display:inline-block; margin:0 4px }
    </style>
</head>
<body>
    <div class="module-container">
        <h2>Materials</h2>
        <div class="card">
                <?php if ($flash): ?><div style="padding:8px;background:#f6ffef;border:1px solid #cfc;margin-bottom:8px"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <div>
                        <?php if (!$project_id): ?>
                            <strong>No project selected.</strong> Provide `project_id` in the URL.
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <a class="button" href="/src/modules/admin-modules/add-material.php?project_id=<?php echo htmlspecialchars($project_id); ?>">Add Materials</a>
                        <form method="post" style="display:flex;gap:8px;align-items:center;margin:0">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                            <input type="hidden" name="action" value="send_request" />
                            <select name="group_id">
                                <option value="">-- All groups --</option>
                                <?php foreach(array_keys($groups) as $gk): ?>
                                    <option value="<?php echo htmlspecialchars($gk); ?>">Group <?php echo htmlspecialchars($gk); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="supplier_id">
                                <option value="">-- Send request to supplier --</option>
                                <?php foreach($suppliers as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['id']); ?>"><?php echo htmlspecialchars($s['full_name'] ?? $s['email']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Send Request</button>
                        </form>
                    </div>
                </div>

                <?php if (empty($rows)): ?>
                    <p>No materials added to this project yet. Click "Add Materials" to select.</p>
                <?php else: ?>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                        <button type="button" id="edit-qty-btn">Edit Quantities</button>
                        <span style="color:#666;font-size:13px">Toggle to edit quantities for all items, then Save All.</span>
                    </div>
                    <form id="bulk-quant-form" method="post">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                        <input type="hidden" name="action" value="save_all" />
                        <?php if (empty($groups)): ?>
                            <p>No materials added to this project yet.</p>
                        <?php else: ?>
                            <?php foreach($groups as $gid => $groupRows): ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px">
                                    <h3 style="margin:0">Group <?php echo htmlspecialchars($gid); ?></h3>
                                    <form method="post" style="display:flex;gap:8px;align-items:center;margin:0">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                                        <input type="hidden" name="action" value="send_request" />
                                        <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($gid); ?>" />
                                        <select name="supplier_id">
                                            <option value="">-- Supplier --</option>
                                            <?php foreach($suppliers as $s): ?>
                                                <option value="<?php echo htmlspecialchars($s['id']); ?>"><?php echo htmlspecialchars($s['full_name'] ?? $s['email']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Send Group Request</button>
                                    </form>
                                </div>
                                <table class="materials-table">
                                    <thead><tr><th>Name</th><th>Quantity</th><th>Unit Price</th><th>Total Price</th><th>Actions</th></tr></thead>
                                    <tbody>
                                    <?php foreach($groupRows as $pm):
                                        $mat = null;
                                        if (!empty($pm['material_id'])) {
                                            $mRes = sb_get_table('materials', 'id=eq.' . urlencode($pm['material_id']));
                                            $mat = isset($mRes['body'][0]) ? $mRes['body'][0] : null;
                                        }
                                        $unit_price = $pm['unit_price'] ?? ($mat['unit_price'] ?? 0);
                                        $quantity = floatval($pm['quantity'] ?? 0);
                                        $total = $quantity * floatval($unit_price);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mat['name'] ?? ('#' . ($pm['material_id'] ?? ''))); ?></td>
                                            <td>
                                                <input type="number" step="any" min="0" name="quantities[<?php echo htmlspecialchars($pm['id']); ?>]" value="<?php echo htmlspecialchars($quantity); ?>" class="bulk-qty-input" disabled style="width:80px" data-original="<?php echo htmlspecialchars($quantity); ?>" />
                                            </td>
                                            <td><?php echo htmlspecialchars(number_format(floatval($unit_price),2)); ?></td>
                                            <td><?php echo htmlspecialchars(number_format($total,2)); ?></td>
                                            <td class="materials-actions">
                                                <form method="post" onsubmit="return confirm('Remove material from project?')">
                                                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                                                    <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($pm['id']); ?>" />
                                                    <button type="submit">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div style="margin-top:8px;display:none" id="bulk-actions">
                            <button type="submit">Save All</button>
                            <button type="button" id="bulk-cancel">Cancel</button>
                        </div>
                    </form>
                <?php endif; ?>
                

            </div>
        </div>
    
    <!-- Modal for Add Materials -->
    <div id="materials-modal" class="materials-modal" aria-hidden="true">
        <div id="materials-modal-backdrop" class="materials-backdrop"></div>
        <div class="card materials-modal-panel">
            <div class="modal-content">Loading...</div>
        </div>
    </div>

    <style>
    .materials-modal { display:none; position:fixed; inset:0; z-index:9999; align-items:center; justify-content:center; }
    .materials-modal.open { display:flex; }
    .materials-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.5); }
    .materials-modal-panel { position:relative; z-index:10000; max-width:900px; width:90%; max-height:80%; overflow:auto; margin:auto; padding:16px; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        console.debug('materials modal init');
        var addBtns = Array.from(document.querySelectorAll('a.button')).filter(function(a){ return a.href && a.href.indexOf('add-material.php') !== -1; });
        console.debug('found add buttons', addBtns.length);
        var modal = document.getElementById('materials-modal');
        if (!modal) { console.debug('no modal element found'); return; }
        var content = modal.querySelector('.modal-content');
        function openModal(url){
            console.debug('openModal', url);
            content.innerHTML = 'Loading...';
            modal.classList.add('open');
            fetch(url).then(function(r){ return r.text(); }).then(function(html){
                content.innerHTML = html;
                var form = content.querySelector('form');
                if(form){
                    form.addEventListener('submit', function(e){
                        e.preventDefault();
                        var fd = new FormData(form);
                        // include submit name so server detects submission
                        if (!fd.has('add_selected')) fd.append('add_selected', '1');
                        var action = form.action || '/src/modules/admin-modules/add-material.php';
                        var postUrl = action.indexOf('?') === -1 ? action + '?embed=1' : action + '&embed=1';
                        fetch(postUrl, { method: 'POST', body: fd }).then(function(r){ return r.text(); }).then(function(resp){
                            content.innerHTML = resp;
                            setTimeout(function(){ location.reload(); }, 700);
                        }).catch(function(err){ console.error('post error', err); });
                    });
                }
            }).catch(function(err){ console.error('fetch error', err); content.innerHTML = 'Failed to load.'; });
        }
        addBtns.forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var url = this.href;
                url = url + (url.indexOf('?')===-1? '?embed=1' : '&embed=1');
                openModal(url);
            });
        });
        // close when clicking backdrop or close button
        modal.addEventListener('click', function(e){
            if(e.target.id === 'materials-modal-backdrop' || e.target.classList.contains('modal-close')){
                modal.classList.remove('open');
            }
        });
        // bulk edit quantities
        var editBtn = document.getElementById('edit-qty-btn');
        var bulkForm = document.getElementById('bulk-quant-form');
        var bulkActions = document.getElementById('bulk-actions');
            if (editBtn && bulkForm) {
            editBtn.addEventListener('click', function(){
                var inputs = bulkForm.querySelectorAll('.bulk-qty-input');
                var isDisabled = inputs.length? inputs[0].disabled : true;
                inputs.forEach(function(i){ i.disabled = !isDisabled; });
                // ensure inputs enforce min=0 and clamp on user input
                inputs.forEach(function(i){
                    try { i.setAttribute('min','0'); } catch(e){}
                    i.addEventListener('input', function(){
                        if (this.value === '-') { this.value = 0; return; }
                        var v = parseFloat(this.value);
                        if (!isNaN(v) && v < 0) this.value = 0;
                        if (this.value === '' ) return; // allow clearing
                    });
                });
                if (isDisabled) {
                    bulkActions.style.display = 'block';
                    editBtn.textContent = 'Stop Editing';
                } else {
                    // cancel changes by reverting to original values
                    inputs.forEach(function(i){ i.value = i.getAttribute('data-original'); i.disabled = true; });
                    bulkActions.style.display = 'none';
                    editBtn.textContent = 'Edit Quantities';
                }
            });
            var cancelBtn = document.getElementById('bulk-cancel');
            if (cancelBtn) cancelBtn.addEventListener('click', function(){ editBtn.click(); });
            // ensure no negative quantities are submitted
            bulkForm.addEventListener('submit', function(e){
                var inputs = bulkForm.querySelectorAll('.bulk-qty-input');
                var corrected = false;
                inputs.forEach(function(i){
                    var v = parseFloat(i.value);
                    if (isNaN(v) || v < 0) {
                        i.value = Math.max(0, isNaN(v)?0:v);
                        corrected = true;
                    }
                });
                if (corrected) {
                    // optionally inform the user we corrected negatives
                    alert('Negative quantities were set to 0 before saving.');
                }
                // allow submit to continue
            });
        }
    });
    </script>
</body>
</html>