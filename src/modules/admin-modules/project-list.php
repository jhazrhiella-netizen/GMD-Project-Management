<?php
require_once __DIR__ . '/../../config.php';
// module: project-list - renders a simple table of projects (no header/sidebar)

// Fetch projects (example) - expects a `projects` table in supabase
$projectsRes = sb_get_table('projects');
$rows = [];
if (isset($projectsRes['body']) && is_array($projectsRes['body'])) $rows = $projectsRes['body'];

// If projects reference clients by `client_id` but `client` column is empty,
// load clients in a single request and map them by id for display.
$clientMap = [];
$clientIds = [];
foreach ($rows as $r) {
    if (empty($r['client']) && !empty($r['client_id'])) $clientIds[] = intval($r['client_id']);
}
$clientIds = array_values(array_unique($clientIds));
if (!empty($clientIds)) {
    // build PostgREST IN list: id=in.(1,2,3)
    $in = implode(',', array_map('intval', $clientIds));
    $q = 'id=in.(' . $in . ')&select=id,name,contact,email';
    $cRes = sb_get_table('clients', $q);
    if (!empty($cRes['body']) && is_array($cRes['body'])) {
        foreach ($cRes['body'] as $c) {
            $clientMap[intval($c['id'])] = $c;
        }
    }
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $filtered = [];
    foreach ($rows as $r) {
        if (stripos($r['name'] ?? '', $q) !== false || stripos($r['description'] ?? '', $q) !== false) $filtered[] = $r;
    }
    $rows = $filtered;
}
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div>
            <strong>Projects</strong>
        </div>
        <button id="openAddProjectInline" type="button">+ Add Project</button>
    </div>

    <form method="get" style="margin-bottom:12px;display:flex;gap:8px;align-items:center">
        <input name="q" placeholder="Search projects" value="<?php echo htmlspecialchars($q); ?>" />
        <button type="submit">Search</button>
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Clear</a>
    </form>

    <?php if (empty($rows)): ?>
        <p>No projects found. Create some in Supabase `projects` table.</p>
    <?php else: ?>
        <div style="overflow:auto">
            <table class="projects-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #ddd">
                        <th style="padding:8px">Name</th>
                        <th style="padding:8px">Client</th>
                        <th style="padding:8px">Status</th>
                        <th style="padding:8px">Description</th>
                        <th style="padding:8px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($rows as $r): ?>
                    <tr style="border-bottom:1px solid #f1f1f1">
                        <td style="padding:8px;vertical-align:top"><a href="/src/admin-pages/project-view.php?id=<?php echo urlencode($r['id']); ?>"><?php echo htmlspecialchars($r['name'] ?? '[no name]'); ?></a></td>
                        <td style="padding:8px;vertical-align:top"><?php
                            $clientName = '';
                            if (!empty($r['client'])) $clientName = $r['client'];
                            elseif (!empty($r['client_id']) && isset($clientMap[intval($r['client_id'])])) $clientName = $clientMap[intval($r['client_id'])]['name'] ?? '';
                            echo htmlspecialchars($clientName);
                        ?></td>
                        <td style="padding:8px;vertical-align:top"><?php echo htmlspecialchars($r['status'] ?? ''); ?></td>
                        <td style="padding:8px;vertical-align:top;color:#555;font-size:13px"><?php echo htmlspecialchars(mb_strimwidth($r['description'] ?? '', 0, 180, '...')); ?></td>
                        <td style="padding:8px;vertical-align:top"><a href="/src/admin-pages/project-view.php?id=<?php echo urlencode($r['id']); ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script>
(function(){
    var btn = document.getElementById('openAddProjectInline');
    if (!btn) return;
    btn.addEventListener('click', function(){
            // First load client-type selector, then load the add-project form based on selection
            fetch('/src/modules/admin-modules/client-type.php', {credentials:'include'})
            .then(function(r){ if (!r.ok) throw new Error('Failed to load client type selector'); return r.text(); })
        .then(function(html){
            var modal = document.createElement('div');
            modal.style.position = 'fixed';
            modal.style.inset = '0';
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            // Inject modal container and put the fetched client-type HTML inside
            modal.innerHTML = '<div style="background:#fff;padding:16px;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow:auto;position:relative">'
                + '<button id="modalCloseInline" style="position:absolute;right:8px;top:8px;font-size:18px">&times;</button>'
                + '<div id="modalBodyInline">' + html + '</div>'
                + '</div>';
            document.body.appendChild(modal);
            // If client-type selector loaded, wire its buttons to load the Add Project form
            (function attachClientTypeHandlers(ctx){
                try {
                    var card = ctx.querySelector('.card');
                    if (!card) return;
                    var buttons = card.querySelectorAll('button');
                    buttons.forEach(function(b){
                        b.addEventListener('click', function(ev){
                            ev.preventDefault();
                            var type = b.getAttribute('data-client-type') || ((b.textContent||'').toLowerCase().indexOf('new') !== -1 ? 'new' : 'existing');
                            if (type === 'existing') {
                                showExistingClientSearch(ctx);
                            } else {
                                // load add-project form into same modal for new client
                                loadAddProject('new', ctx);
                            }
                        });
                    });
                } catch(e){ console.warn('attachClientTypeHandlers', e); }
            })(modal);

            // wire modal close button
            var closeBtn = modal.querySelector('#modalCloseInline');
            if (closeBtn) closeBtn.addEventListener('click', function(){ document.body.removeChild(modal); });

            // Show search UI to pick existing client
            function showExistingClientSearch(ctx){
                var body = ctx.querySelector('#modalBodyInline');
                if (!body) return;
                body.innerHTML = '<div class="card"><h4>Find Existing Client</h4><input id="clientSearch" placeholder="Type client name" style="width:100%;padding:8px;margin-bottom:8px" /><div id="clientSuggestions" style="max-height:260px;overflow:auto"></div></div>';
                var input = body.querySelector('#clientSearch');
                var suggestions = body.querySelector('#clientSuggestions');
                var debounce = null;
                input.addEventListener('input', function(){
                    var val = input.value.trim();
                    if (debounce) clearTimeout(debounce);
                    if (val.length < 2) { suggestions.innerHTML = ''; return; }
                    debounce = setTimeout(function(){
                        fetch('/src/modules/admin-modules/search-clients.php?q=' + encodeURIComponent(val), {credentials:'include'})
                        .then(function(r){ if (!r.ok) throw new Error('Search failed'); return r.json(); })
                        .then(function(data){
                            var list = (data.body && Array.isArray(data.body)) ? data.body : (data || []);
                            suggestions.innerHTML = list.map(function(c){
                                var html = '<div class="client-suggestion" data-id="'+(c.id||'')+'" style="padding:8px;border-bottom:1px solid #eee;cursor:pointer">'
                                    + '<strong>' + (c.name||'') + '</strong> <span style="color:#666">' + (c.email?(' — ' + c.email):'') + '</span>'
                                    + '<div style="font-size:12px;color:#777">' + (c.contact?c.contact:'') + (c.address?(' — ' + c.address):'') + '</div>'
                                    + '</div>';
                                return html;
                            }).join('');
                            // attach click handlers
                            suggestions.querySelectorAll('.client-suggestion').forEach(function(el){
                                el.addEventListener('click', function(){
                                    var id = el.getAttribute('data-id');
                                    if (!id) return;
                                    // load add-project prefilled with client id
                                    loadAddProject('existing', ctx, id);
                                });
                            });
                        }).catch(function(err){ console.error('client search error', err); suggestions.innerHTML = '<div style="color:#900">Search failed</div>'; });
                    }, 300);
                });
            }

            // Load add-project.php into modal and initialize it
            function loadAddProject(clientType, ctx, clientId){
                var postUrl = '/src/modules/admin-modules/add-project.php?client_type=' + encodeURIComponent(clientType);
                if (clientId) postUrl += '&client_id=' + encodeURIComponent(clientId);
                fetch(postUrl, {credentials:'include'})
                .then(function(r){ if (!r.ok) throw new Error('Failed to load add project form'); return r.text(); })
                .then(function(html2){
                    var body = ctx.querySelector('#modalBodyInline');
                    if (!body) return;
                    body.innerHTML = html2;
                    // after injecting add-project form, initialize UI and submit handler
                    try {
                        // drawer toggles
                        body.querySelectorAll('.drawer-toggle').forEach(function(btn){
                            btn.addEventListener('click', function(){
                                var bd = btn.nextElementSibling; if (!bd) return; bd.style.display = (bd.style.display === 'none' || bd.style.display === '') ? 'block' : 'none';
                            });
                        });
                    } catch(e){}
                    // initialize address dropdowns if present
                    (function initAddress(ctx2){
                        var regionEl = ctx2.querySelector('#region');
                        var provinceEl = ctx2.querySelector('#province');
                        var cityEl = ctx2.querySelector('#city');
                        var barangayEl = ctx2.querySelector('#barangay');
                        function loadJSON(path){ return fetch(path).then(function(r){ if (!r.ok) throw new Error('Failed'); return r.json(); }); }
                        if (regionEl && provinceEl && cityEl && barangayEl) {
                            Promise.all([
                                loadJSON('/src/ph-json/region.json'),
                                loadJSON('/src/ph-json/province.json'),
                                loadJSON('/src/ph-json/city.json'),
                                loadJSON('/src/ph-json/barangay.json')
                            ]).then(function(all){
                                var regions = all[0], provinces = all[1], cities = all[2], barangays = all[3];
                                regionEl.innerHTML = '<option value="">Select region</option>' + regions.map(function(r){ return '<option value="'+(r.region_code||r.psgc_code||r.id)+'" data-name="'+(r.region_name||r.region)+'">'+(r.region_name||r.region)+'</option>'; }).join('');
                                regionEl.addEventListener('change', function(){
                                    var rc = regionEl.value; var provs = provinces.filter(function(p){ return (p.region_code == rc) || (p.region_code == (rc)); });
                                    provinceEl.innerHTML = '<option value="">Select province</option>' + provs.map(function(p){ return '<option value="'+(p.province_code||p.psgc_code)+'" data-name="'+(p.province_name||p.name||p.province)+'">'+(p.province_name||p.name||p.province)+'</option>'; }).join('');
                                    cityEl.innerHTML = '<option value="">Select city/municipality</option>'; barangayEl.innerHTML = '<option value="">Select barangay</option>'; });
                                provinceEl.addEventListener('change', function(){ var provCode = provinceEl.value; var cityList = cities.filter(function(c){ return (c.province_code == provCode) || (c.prov_code == provCode) || (c.province_psgc == provCode); }); cityEl.innerHTML = '<option value="">Select city/municipality</option>' + cityList.map(function(c){ var label = c.city_municipality_name || c.city_name || c.municipality_name || c.name || c.city; var val = c.city_municipality_code || c.city_code || c.id || label; return '<option value="'+val+'" data-name="'+label+'">'+label+'</option>'; }).join(''); barangayEl.innerHTML = '<option value="">Select barangay</option>'; });
                                cityEl.addEventListener('change', function(){ var cityCode = cityEl.value; var bList = barangays.filter(function(b){ return (b.city_municipality_code == cityCode) || (b.city_code == cityCode) || (b.citymun_code == cityCode); }); barangayEl.innerHTML = '<option value="">Select barangay</option>' + bList.map(function(b){ var label = b.brgy_name || b.barangay || b.name; var val = b.brgy_code || b.brgy_code_alt || b.id || label; return '<option value="'+val+'" data-name="'+label+'">'+label+'</option>'; }).join(''); });
                                function updateHiddenNames(){ var rOpt = regionEl.selectedOptions[0]; if (rOpt) { var el = ctx2.querySelector('#client_region_name'); if (el) el.value = rOpt.textContent; } var pOpt = provinceEl.selectedOptions[0]; if (pOpt) { var el2 = ctx2.querySelector('#client_province_name'); if (el2) el2.value = pOpt.textContent; } var cOpt = cityEl.selectedOptions[0]; if (cOpt) { var el3 = ctx2.querySelector('#client_city_name'); if (el3) el3.value = cOpt.textContent; } var bOpt = barangayEl.selectedOptions[0]; if (bOpt) { var el4 = ctx2.querySelector('#client_barangay_name'); if (el4) el4.value = bOpt.textContent; } }
                                regionEl.addEventListener('change', updateHiddenNames); provinceEl.addEventListener('change', updateHiddenNames); cityEl.addEventListener('change', updateHiddenNames); barangayEl.addEventListener('change', updateHiddenNames); updateHiddenNames();

                                // Prefill codes when provided by server-side hidden inputs
                                try {
                                    var pre_region = (ctx.querySelector('input[name="prefill_client_region"]')||{value:''}).value || '';
                                    var pre_province = (ctx.querySelector('input[name="prefill_client_province"]')||{value:''}).value || '';
                                    var pre_city = (ctx.querySelector('input[name="prefill_client_city"]')||{value:''}).value || '';
                                    var pre_barangay = (ctx.querySelector('input[name="prefill_client_barangay"]')||{value:''}).value || '';
                                    var existingClient = !!(ctx.querySelector('input[name="existing_client_id"]'));

                                    function waitAndSet(el, value, attempts, interval, cb){ if (!value) return cb && cb(); attempts = attempts || 8; interval = interval || 80; var tryOnce = function(att){ var found = Array.prototype.slice.call(el.options).some(function(o){ return String(o.value) === String(value); }); if (found){ el.value = value; el.dispatchEvent(new Event('change')); return cb && cb(); } if (att <= 0) return cb && cb(); setTimeout(function(){ tryOnce(att-1); }, interval); }; tryOnce(attempts); }

                                    waitAndSet(regionEl, pre_region, 12, 60, function(){
                                        waitAndSet(provinceEl, pre_province, 12, 60, function(){
                                            waitAndSet(cityEl, pre_city, 12, 60, function(){
                                                waitAndSet(barangayEl, pre_barangay, 12, 60, function(){
                                                    // Lock client inputs if existing client
                                                    if (existingClient) {
                                                        try {
                                                            ['client_name','client_contact','client_email'].forEach(function(n){ var el = ctx.querySelector('[name="'+n+'"]'); if (el) el.readOnly = true; });
                                                            ['client_region','client_province','client_city','client_barangay'].forEach(function(n){ var s = ctx.querySelector('[name="'+n+'"]'); if (s){ s.disabled = true; var hf = document.createElement('input'); hf.type='hidden'; hf.name = n; hf.value = s.value; s.form.appendChild(hf); } });
                                                        } catch(e){}
                                                    }
                                                });
                                            });
                                        });
                                    });
                                } catch(e){}
                            }).catch(function(err){ console.warn('Address JSON load failed', err); });
                        }
                    })(body);

                    // find form and attach submit handler (same logic as before)
                    var form2 = body.querySelector('form');
                    if (form2) {
                        try { form2.noValidate = true; } catch(e) {}
                        // insert hidden client_type field
                        try { if (!form2.querySelector('input[name="client_type"]')) { var hf = document.createElement('input'); hf.type='hidden'; hf.name='client_type'; hf.value=clientType; form2.appendChild(hf); } } catch(e) {}
                        form2.addEventListener('submit', function(e){
                            e.preventDefault();
                            var fd = new FormData(form2);
                            var postUrl = form2.action || window.location.href;
                            if (postUrl.indexOf('?') === -1) postUrl += '?_debug=1'; else postUrl += '&_debug=1';
                            var headers = {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'};
                            try { var csrfVal = fd.get('_csrf'); if (csrfVal) headers['X-CSRF-Token'] = csrfVal; } catch(e) {}
                            try { if (!fd.has('add_project')) fd.append('add_project','1'); } catch(e) {}
                            fetch(postUrl, {method:'POST', body: fd, credentials:'include', headers: headers})
                            .then(function(res){ return res.text().then(function(text){ try { return JSON.parse(text); } catch(err){ return { ok:false, errors:['Invalid server response'], raw:text, status: res.status }; } }); })
                            .then(function(data){ if (data.ok) { if (data.redirect) window.location.href = data.redirect; else window.location.reload(); } else { var lines=[]; if (data.errors && data.errors.length) lines = lines.concat(data.errors.map(function(it){ return String(it); })); if (data.server_error) lines.push('Server error: ' + String(data.server_error)); if (data.error) lines.push('Error: ' + String(data.error)); if (data.message) lines.push('Message: ' + String(data.message)); if (data.status) lines.push('HTTP status: ' + String(data.status)); var eHtml = '<div class="modal-error"><ul>' + lines.map(function(it){ return '<li>'+ it +'</li>'; }).join('') + '</ul>'; if (data.raw) eHtml += '<div style="margin-top:8px"><strong>Raw response:</strong><pre style="white-space:pre-wrap;max-height:240px;overflow:auto;background:#f8f8f8;border:1px solid #eee;padding:8px">'+escapeHtml(String(data.raw))+'</pre></div>'; if (data.debug) eHtml += '<div style="margin-top:8px"><strong>Debug:</strong><pre style="white-space:pre-wrap;max-height:240px;overflow:auto;background:#f4f4ff;border:1px solid #e6e6ff;padding:8px">'+escapeHtml(JSON.stringify(data.debug, null, 2))+'</pre></div>'; eHtml += '</div>'; var target = ctx.querySelector('.card') || ctx.querySelector('#modalBodyInline'); if (target) target.insertAdjacentHTML('afterbegin', eHtml); } }).catch(function(err){ console.error(err); alert('Failed to submit form: ' + err.message); });
                        });
                    }

                }).catch(function(err){ console.error('loadAddProject failed', err); alert('Failed to load Add Project form: ' + err.message); });
            }
        }).catch(function(err){ alert('Failed to load form: ' + err.message); });
    });
})();
// helper to escape HTML for safe display
function escapeHtml(s){ return s.replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
</script>
