<?php
require_once __DIR__ . '/../../config.php';
require_login();

$errors = [];
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $token = $_POST['_csrf'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
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

            $res = sb_insert_table('projects', $data);
            if (isset($res['status']) && ($res['status'] == 201 || $res['status'] == 200)) {
                $newId = $res['body'][0]['id'] ?? null;
                if ($newId) {
                    header('Location: /src/admin-pages/project.php');
                    exit;
                }
                $success_msg = 'Project added successfully.';
            } else {
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

    <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
        <div class="drawer">
            <button type="button" class="drawer-toggle">Client Details</button>
            <div class="drawer-body" style="display:none;padding:8px;border:1px solid #eee;margin-top:6px">
                <label>Client Name</label>
                <input name="client_name" value="<?php echo htmlspecialchars($client_name ?? ''); ?>" required />

                <label>Address</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <select id="region" name="client_region" style="min-width:200px"></select>
                    <select id="province" name="client_province" style="min-width:200px"></select>
                    <select id="city" name="client_city" style="min-width:200px"></select>
                    <select id="barangay" name="client_barangay" style="min-width:200px"></select>
                </div>
                <input type="hidden" name="client_region_name" id="client_region_name" />
                <input type="hidden" name="client_province_name" id="client_province_name" />
                <input type="hidden" name="client_city_name" id="client_city_name" />
                <input type="hidden" name="client_barangay_name" id="client_barangay_name" />

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
                <input name="project_name" value="<?php echo htmlspecialchars($project_name ?? ''); ?>" required />

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
