<?php
require_once __DIR__ . '/../../config.php';
require_login();

$errors = [];
$success_msg = '';

// Debug mode: enabled when APP_ENV=development, DEBUG=1, or ?_debug=1
$debug = ((getenv('APP_ENV') === 'development') || (getenv('DEBUG') === '1') || isset($_GET['_debug']));

// If requested with a client_id, prefill client fields for existing client
$prefill_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;
if ($prefill_client_id) {
    $cRes = sb_get_table('clients', 'id=eq.' . $prefill_client_id . '&select=*');
    if (!empty($cRes['body'][0])) {
        $c = $cRes['body'][0];
        $client_name = $c['name'] ?? '';
        $client_contact = $c['contact'] ?? '';
        $client_email = $c['email'] ?? '';
        $client_region = $c['client_region_code'] ?? '';
        $client_province = $c['client_province_code'] ?? '';
        $client_city = $c['client_city_code'] ?? '';
        $client_barangay = $c['client_barangay_code'] ?? '';
        $client_region_name = $c['client_region_name'] ?? '';
        $client_province_name = $c['client_province_name'] ?? '';
        $client_city_name = $c['client_city_name'] ?? '';
        $client_barangay_name = $c['client_barangay_name'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $token = $_POST['_csrf'] ?? '';
    // detect AJAX early so we can return JSON on CSRF failure
    $isAjax = false;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') $isAjax = true;
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) $isAjax = true;

    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        // prepare diagnostics for AJAX callers when debug enabled
        if (!isset($diagnostics) || !is_array($diagnostics)) $diagnostics = [];
        $diagnostics['post_token'] = $token;
        $diagnostics['session'] = isset($_SESSION) ? $_SESSION : null;
        // Also include raw cookie header if present
        $diagnostics['cookie_header'] = $_SERVER['HTTP_COOKIE'] ?? null;
        if ($isAjax) {
            header('Content-Type: application/json');
            $payload = ['ok' => false, 'errors' => ['Invalid form submission.'], 'debug' => ($debug ? $diagnostics : null)];
            echo json_encode($payload);
            exit;
        }
        $errors[] = 'Invalid form submission.';
    } else {
        $project_name = trim($_POST['project_name'] ?? '');
        $budget = trim($_POST['budget'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $lead_time = trim($_POST['lead_time'] ?? '');

        $client_name = trim($_POST['client_name'] ?? '');
        $client_region = trim($_POST['client_region'] ?? '');
        $client_province = trim($_POST['client_province'] ?? '');
        $client_city = trim($_POST['client_city'] ?? '');
        $client_barangay = trim($_POST['client_barangay'] ?? '');
        $client_region_name = trim($_POST['client_region_name'] ?? '');
        $client_province_name = trim($_POST['client_province_name'] ?? '');
        $client_city_name = trim($_POST['client_city_name'] ?? '');
        $client_barangay_name = trim($_POST['client_barangay_name'] ?? '');
        $client_contact = trim($_POST['client_contact'] ?? '');
        $client_email = trim($_POST['client_email'] ?? '');

        if ($project_name === '') $errors[] = 'Project name is required.';
        if ($client_name === '') $errors[] = 'Client name is required.';

        if (empty($errors)) {
            $client_address_parts = array_filter([$client_barangay, $client_city, $client_province, $client_region]);
            $client_address = implode(', ', $client_address_parts);

            $data = [
                'name' => $project_name,
                'client' => $client_name,
                'client_address' => $client_address,
                'client_region_code' => $client_region !== '' ? $client_region : null,
                'client_province_code' => $client_province !== '' ? $client_province : null,
                'client_city_code' => $client_city !== '' ? $client_city : null,
                'client_barangay_code' => $client_barangay !== '' ? $client_barangay : null,
                'client_region_name' => $client_region_name !== '' ? $client_region_name : null,
                'client_province_name' => $client_province_name !== '' ? $client_province_name : null,
                'client_city_name' => $client_city_name !== '' ? $client_city_name : null,
                'client_barangay_name' => $client_barangay_name !== '' ? $client_barangay_name : null,
                'client_contact' => $client_contact,
                'client_email' => $client_email,
                'budget' => $budget !== '' ? floatval($budget) : null,
                'start_date' => $start_date ?: null,
                'lead_time' => $lead_time ?: null,
                'status' => 'planned'
            ];

            // Remove null fields to avoid PostgREST rejecting unknown columns present with null
            $insertData = [];
            foreach ($data as $k => $v) {
                if ($v !== null) $insertData[$k] = $v;
            }

            // detect AJAX requests early (used for returning JSON on client errors)
            $isAjax = false;
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') $isAjax = true;
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) $isAjax = true;

            // --- CLIENT: find or create client record in `clients` table and set client_id ---
            $client_id = null;
            if (!isset($diagnostics) || !is_array($diagnostics)) $diagnostics = [];
            $diagnostics['client_lookup'] = null;
            // prefer lookup by email when provided
            if (!empty($client_email)) {
                $q = 'email=eq.' . rawurlencode($client_email) . '&select=id';
                $cRes = sb_get_table('clients', $q);
                $diagnostics['client_lookup']['by_email'] = $cRes;
                if (!empty($cRes['body'][0]['id'])) $client_id = $cRes['body'][0]['id'];
            }
            // fallback: lookup by exact name (case-insensitive match not supported via simple eq)
            if ($client_id === null && !empty($client_name)) {
                $q = 'name=eq.' . rawurlencode($client_name) . '&select=id';
                $cRes2 = sb_get_table('clients', $q);
                $diagnostics['client_lookup']['by_name'] = $cRes2;
                if (!empty($cRes2['body'][0]['id'])) $client_id = $cRes2['body'][0]['id'];
            }

            // create client if not found
            if ($client_id === null) {
                $clientPayload = [
                    'name' => $client_name ?: 'Unknown',
                    'address' => $client_address ?: null,
                    'client_region_code' => $client_region ?: null,
                    'client_province_code' => $client_province ?: null,
                    'client_city_code' => $client_city ?: null,
                    'client_barangay_code' => $client_barangay ?: null,
                    'client_region_name' => $client_region_name ?: null,
                    'client_province_name' => $client_province_name ?: null,
                    'client_city_name' => $client_city_name ?: null,
                    'client_barangay_name' => $client_barangay_name ?: null,
                    'contact' => $client_contact ?: null,
                    'email' => $client_email ?: null
                ];
                // strip nulls
                $clientInsert = [];
                foreach ($clientPayload as $k => $v) if ($v !== null) $clientInsert[$k] = $v;
                if (!empty($clientInsert)) {
                    $cInsRes = sb_insert_table('clients', $clientInsert);
                    $diagnostics['client_create'] = $cInsRes;
                    // append server-side log for debugging (safe: temp file only)
                    try {
                        $logfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gmd_add_project.log';
                        $entry = ['time' => date('c'), 'action' => 'client_create', 'payload' => $clientInsert, 'response' => $cInsRes, 'session_id' => session_id()];
                        file_put_contents($logfile, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
                    } catch (Exception $e) { /* ignore logging errors */ }
                    if (!empty($cInsRes['body'][0]['id'])) {
                        $client_id = $cInsRes['body'][0]['id'];
                    } else {
                        // creation failed — surface error for AJAX callers
                        $errMsg = 'Failed to create client record.';
                        if (!empty($cInsRes['error'])) $errMsg .= ' ' . $cInsRes['error'];
                        if ($isAjax) {
                            header('Content-Type: application/json');
                            $payload = ['ok' => false, 'errors' => [$errMsg], 'server_error' => $cInsRes['error'] ?? null, 'status' => $cInsRes['status'] ?? null, 'debug' => $diagnostics];
                            echo json_encode($payload);
                            exit;
                        }
                        $errors[] = $errMsg;
                    }
                }
            }

            // attach client_id to project payload if available
            if ($client_id !== null) {
                $insertData['client_id'] = $client_id;
            }

            // Remove legacy client fields from project payload (projects now use client_id)
            foreach (['client','client_address','client_region_code','client_province_code','client_city_code','client_barangay_code','client_region_name','client_province_name','client_city_name','client_barangay_name','client_contact','client_email'] as $legacy) {
                if (isset($insertData[$legacy])) unset($insertData[$legacy]);
            }

            // Attempt insert and collect diagnostics
            $res = sb_insert_table('projects', $insertData);

            // prepare diagnostics (preserve earlier diagnostics like client_create)
            if (!isset($diagnostics) || !is_array($diagnostics)) $diagnostics = [];
            // capture request headers (if available)
            if (function_exists('getallheaders')) {
                $diagnostics['headers'] = getallheaders();
            } else {
                $diagnostics['server'] = array_intersect_key($_SERVER, array_flip(['HTTP_COOKIE','HTTP_ACCEPT','HTTP_USER_AGENT','HTTP_REFERER','HTTP_HOST','HTTP_ORIGIN','HTTP_X_REQUESTED_WITH']));
            }
            $diagnostics['raw_input'] = @file_get_contents('php://input');
            $diagnostics['post'] = $_POST;
            $diagnostics['session'] = isset($_SESSION) ? (array)$_SESSION : null;
            $diagnostics['csrf_ok'] = (function_exists('verify_csrf_token') ? verify_csrf_token($token) : null);
            $diagnostics['insert_payload'] = $insertData;
            $diagnostics['project_insert'] = $res;

            // also append project insert result to temp log for troubleshooting
            try {
                $logfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gmd_add_project.log';
                $entry = ['time' => date('c'), 'action' => 'project_insert', 'payload' => $insertData, 'response' => $res, 'session_id' => session_id()];
                file_put_contents($logfile, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
            } catch (Exception $e) { }

            // If insert failed and we're in debug, attempt to infer columns from local SQL migrations
            if (!$res['ok'] && $debug) {
                $sqlFiles = [
                    __DIR__ . '/../../migrations/current_database.sql',
                    __DIR__ . '/../../migrations/20260514_add_project_address_codes.sql'
                ];
                $cols = [];
                foreach ($sqlFiles as $f) {
                    if (!is_readable($f)) continue;
                    $txt = file_get_contents($f);
                    if (!$txt) continue;
                    // parse create table projects (...) block
                    if (preg_match('/create table if not exists projects \((.*?)\);/is', $txt, $m)) {
                        $inside = $m[1];
                        // find column names at line starts: name type
                        if (preg_match_all('/^\s*([a-zA-Z0-9_]+)\s+/m', $inside, $m2)) {
                            foreach ($m2[1] as $c) $cols[$c] = true;
                        }
                    }
                    // parse ALTER TABLE ... ADD COLUMN lines
                    if (preg_match_all('/ALTER TABLE projects\s+ADD COLUMN IF NOT EXISTS\s+([a-zA-Z0-9_]+)/i', $txt, $m3)) {
                        foreach ($m3[1] as $c) $cols[$c] = true;
                    }
                }
                $diagnostics['inferred_project_columns'] = array_values(array_keys($cols));
                // If we inferred columns, build a filtered payload and retry insert
                if (!empty($cols)) {
                    $filtered = [];
                    foreach ($insertData as $k => $v) {
                        if (isset($cols[$k])) $filtered[$k] = $v;
                    }
                    $diagnostics['filtered_payload'] = $filtered;
                    if (!empty($filtered)) {
                        $retry = sb_insert_table('projects', $filtered);
                        $diagnostics['retry_response'] = $retry;
                        // if retry succeeded, set res to retry so normal flow continues
                        $res = $retry;
                        $diagnostics['sb_response_after_retry'] = $res;
                    }
                }
            }

            $resOk = $res['ok'] ?? (isset($res['status']) && $res['status'] >= 200 && $res['status'] < 300);

            if ($resOk) {
                $newId = $res['body'][0]['id'] ?? null;
                if ($newId) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        $out = ['ok' => true, 'id' => $newId, 'redirect' => '/src/admin-pages/project.php'];
                        if ($debug) $out['debug'] = $diagnostics;
                        echo json_encode($out);
                        exit;
                    }
                    header('Location: /src/admin-pages/project.php');
                    exit;
                }
                $success_msg = 'Project added successfully.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    $out = ['ok' => true, 'message' => $success_msg];
                    if ($debug) $out['debug'] = $diagnostics;
                    echo json_encode($out);
                    exit;
                }
            } else {
                $errors[] = 'Failed to add project.';
                if ($isAjax) {
                    $payload = ['ok' => false, 'errors' => $errors];
                    if (isset($res['error'])) $payload['server_error'] = $res['error'];
                    if (isset($res['status'])) $payload['status'] = $res['status'];
                    // always include diagnostics in debug mode
                    if ($debug) $payload['debug'] = $diagnostics;
                    header('Content-Type: application/json');
                    echo json_encode($payload);
                    exit;
                }
                $errors[] = 'Failed to add project.';
            }
        }
    }
}
?>

<div class="card">
    <h3>Add Project</h3>
    <?php if (!empty($errors)): ?>
        <div style="padding:8px;background:#fff0f0;border:1px solid #f5c2c2;margin-bottom:8px">
            <ul style="margin:0;padding-left:18px">
            <?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($success_msg)): ?>
        <div style="padding:8px;background:#f6ffef;border:1px solid #cfc;margin-bottom:8px"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <form id="addProjectForm" method="post" action="/src/modules/admin-modules/add-project.php">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
        <?php if (!empty($prefill_client_id)): ?>
            <input type="hidden" name="existing_client_id" value="<?php echo intval($prefill_client_id); ?>" />
        <?php endif; ?>
        <div class="drawer">
            <button type="button" class="drawer-toggle">Client Details</button>
            <div class="drawer-body" style="display:none;padding:8px;border:1px solid #eee;margin-top:6px">
                <label>Client Name</label>
                <input name="client_name" value="<?php echo htmlspecialchars($client_name ?? ''); ?>" />

                <label>Address</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <select id="region" name="client_region" style="min-width:200px"></select>
                    <select id="province" name="client_province" style="min-width:200px"></select>
                    <select id="city" name="client_city" style="min-width:200px"></select>
                    <select id="barangay" name="client_barangay" style="min-width:200px"></select>
                </div>
                <input type="hidden" name="client_region_name" id="client_region_name" value="<?php echo htmlspecialchars($client_region_name ?? ''); ?>" />
                <input type="hidden" name="client_province_name" id="client_province_name" value="<?php echo htmlspecialchars($client_province_name ?? ''); ?>" />
                <input type="hidden" name="client_city_name" id="client_city_name" value="<?php echo htmlspecialchars($client_city_name ?? ''); ?>" />
                <input type="hidden" name="client_barangay_name" id="client_barangay_name" value="<?php echo htmlspecialchars($client_barangay_name ?? ''); ?>" />
                <input type="hidden" name="prefill_client_region" id="prefill_client_region" value="<?php echo htmlspecialchars($client_region ?? ''); ?>" />
                <input type="hidden" name="prefill_client_province" id="prefill_client_province" value="<?php echo htmlspecialchars($client_province ?? ''); ?>" />
                <input type="hidden" name="prefill_client_city" id="prefill_client_city" value="<?php echo htmlspecialchars($client_city ?? ''); ?>" />
                <input type="hidden" name="prefill_client_barangay" id="prefill_client_barangay" value="<?php echo htmlspecialchars($client_barangay ?? ''); ?>" />

                <label>Contact Number</label>
                <input name="client_contact" value="<?php echo htmlspecialchars($client_contact ?? ''); ?>" />
                <label>Email</label>
                <input name="client_email" type="email" value="<?php echo htmlspecialchars($client_email ?? ''); ?>" />
            </div>
        </div>

        <div class="drawer" style="margin-top:12px">
            <button type="button" class="drawer-toggle">Project Details</button>
            <div class="drawer-body" style="display:none;padding:8px;border:1px solid #eee;margin-top:6px">
                <label>Project Name</label>
                <input name="project_name" value="<?php echo htmlspecialchars($project_name ?? ''); ?>" />

                <label>Budget</label>
                <input name="budget" value="<?php echo htmlspecialchars($budget ?? ''); ?>" />

                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>" />

                <label>Lead Time (days)</label>
                <input name="lead_time" value="<?php echo htmlspecialchars($lead_time ?? ''); ?>" />
            </div>
        </div>

        <div style="margin-top:12px">
            <button type="submit" name="add_project">Create Project</button>
        </div>
    </form>

</div>

<script>
// Drawer toggles
document.querySelectorAll('.drawer-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
        var body = btn.nextElementSibling;
        if (!body) return;
        if (body.style.display === 'none' || body.style.display === '') body.style.display = 'block'; else body.style.display = 'none';
    });
});

// Cascading address dropdowns using src/ph-json
var regionEl = document.getElementById('region');
var provinceEl = document.getElementById('province');
var cityEl = document.getElementById('city');
var barangayEl = document.getElementById('barangay');

function loadJSON(path){
    return fetch(path).then(function(r){ if (!r.ok) throw new Error('Failed'); return r.json(); });
}

Promise.all([
    loadJSON('/src/ph-json/region.json'),
    loadJSON('/src/ph-json/province.json'),
    loadJSON('/src/ph-json/city.json'),
    loadJSON('/src/ph-json/barangay.json')
]).then(function(all){
    var regions = all[0];
    var provinces = all[1];
    var cities = all[2];
    var barangays = all[3];

    // populate regions
    regionEl.innerHTML = '<option value="">Select region</option>' + regions.map(function(r){ return '<option value="'+(r.region_code||r.psgc_code||r.id)+'" data-name="'+(r.region_name||r.region)+'">'+(r.region_name||r.region)+'</option>'; }).join('');

    // restore prefilled selections when provided by server
    (function restorePrefill(){
        try {
            var pre_region = '<?php echo addslashes($client_region ?? ''); ?>';
            var pre_province = '<?php echo addslashes($client_province ?? ''); ?>';
            var pre_city = '<?php echo addslashes($client_city ?? ''); ?>';
            var pre_barangay = '<?php echo addslashes($client_barangay ?? ''); ?>';
            var existingClient = document.querySelector('input[name="existing_client_id"]') ? true : false;

            function waitAndSet(el, value, attempts, interval, cb){
                if (!value) return cb && cb();
                attempts = attempts || 8; interval = interval || 80;
                var tryOnce = function(att){
                    var found = Array.prototype.slice.call(el.options).some(function(o){ return String(o.value) === String(value); });
                    if (found){ el.value = value; el.dispatchEvent(new Event('change')); return cb && cb(); }
                    if (att <= 0) return cb && cb();
                    setTimeout(function(){ tryOnce(att-1); }, interval);
                };
                tryOnce(attempts);
            }

            // Chain setting region -> province -> city -> barangay
            waitAndSet(regionEl, pre_region, 12, 60, function(){
                waitAndSet(provinceEl, pre_province, 12, 60, function(){
                    waitAndSet(cityEl, pre_city, 12, 60, function(){
                        waitAndSet(barangayEl, pre_barangay, 12, 60, function(){
                            // after restoring values, if this was an existing client selection, lock fields
                            if (existingClient) {
                                try {
                                    // make text inputs readonly so values are still submitted
                                    ['client_name','client_contact','client_email'].forEach(function(n){ var el = document.querySelector('[name="'+n+'"]'); if (el) el.readOnly = true; });
                                    // disable selects but mirror their values with hidden inputs so values are submitted
                                    ['client_region','client_province','client_city','client_barangay'].forEach(function(n){ var s = document.querySelector('[name="'+n+'"]'); if (s){ s.disabled = true; var hf = document.createElement('input'); hf.type='hidden'; hf.name = n; hf.value = s.value; s.form.appendChild(hf); } });
                                } catch(e){}
                            }
                        });
                    });
                });
            });
        } catch(e){}
    })();

    regionEl.addEventListener('change', function(){
        var rc = regionEl.value;
        var provs = provinces.filter(function(p){ return (p.region_code == rc) || (p.region_code == (rc)); });
        provinceEl.innerHTML = '<option value="">Select province</option>' + provs.map(function(p){ return '<option value="'+(p.province_code||p.psgc_code)+'" data-name="'+(p.province_name||p.name||p.province)+'">'+(p.province_name||p.name||p.province)+'</option>'; }).join('');
        cityEl.innerHTML = '<option value="">Select city/municipality</option>';
        barangayEl.innerHTML = '<option value="">Select barangay</option>';
    });

    provinceEl.addEventListener('change', function(){
        var provCode = provinceEl.value;
        var cityList = cities.filter(function(c){ return (c.province_code == provCode) || (c.prov_code == provCode) || (c.province_psgc == provCode); });
        cityEl.innerHTML = '<option value="">Select city/municipality</option>' + cityList.map(function(c){
            var label = c.city_municipality_name || c.city_name || c.municipality_name || c.name || c.city;
            var val = c.city_municipality_code || c.city_code || c.id || label;
            return '<option value="'+val+'" data-name="'+label+'">'+label+'</option>';
        }).join('');
        barangayEl.innerHTML = '<option value="">Select barangay</option>';
    });

    cityEl.addEventListener('change', function(){
        var cityCode = cityEl.value;
        var bList = barangays.filter(function(b){ return (b.city_municipality_code == cityCode) || (b.city_code == cityCode) || (b.citymun_code == cityCode); });
        barangayEl.innerHTML = '<option value="">Select barangay</option>' + bList.map(function(b){ var label = b.brgy_name || b.barangay || b.name; var val = b.brgy_code || b.brgy_code_alt || b.id || label; return '<option value="'+val+'" data-name="'+label+'">'+label+'</option>'; }).join('');
    });

    // ensure hidden name fields update on selection
    function updateHiddenNames(){
        var rOpt = regionEl.selectedOptions[0]; if (rOpt) document.getElementById('client_region_name').value = rOpt.textContent;
        var pOpt = provinceEl.selectedOptions[0]; if (pOpt) document.getElementById('client_province_name').value = pOpt.textContent;
        var cOpt = cityEl.selectedOptions[0]; if (cOpt) document.getElementById('client_city_name').value = cOpt.textContent;
        var bOpt = barangayEl.selectedOptions[0]; if (bOpt) document.getElementById('client_barangay_name').value = bOpt.textContent;
    }
    regionEl.addEventListener('change', updateHiddenNames);
    provinceEl.addEventListener('change', updateHiddenNames);
    cityEl.addEventListener('change', updateHiddenNames);
    barangayEl.addEventListener('change', updateHiddenNames);

    // set initial hidden values
    updateHiddenNames();

}).catch(function(err){
    console.warn('Address JSON load failed', err);
});

</script>
<style>
/* Modal support when loaded via AJAX */
.modal-error{padding:8px;background:#fff0f0;border:1px solid #f5c2c2;margin-bottom:8px}
.modal-success{padding:8px;background:#f6ffef;border:1px solid #cfc;margin-bottom:8px}
</style>
