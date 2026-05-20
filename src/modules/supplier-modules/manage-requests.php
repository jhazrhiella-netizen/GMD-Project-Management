<?php
require_once __DIR__ . '/../../config.php';
require_login();

$currentUser = get_current_user();
$supplier_id = $currentUser['id'] ?? '';

// handle POST actions: set_price, accept, reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        $_SESSION['flash'] = 'Invalid form submission.';
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }
    $action = $_POST['action'] ?? '';
    $req_id = intval($_POST['request_id'] ?? 0);
    if (!$req_id) {
        $_SESSION['flash'] = 'Invalid request id.';
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }
    $checkRes = sb_get_table('material_requests', 'id=eq.' . urlencode($req_id));
    if (is_array($checkRes) && isset($checkRes['body']) && is_array($checkRes['body'])) {
        $reqRecord = $checkRes['body'][0] ?? null;
    } else {
        $reqRecord = null;
    }
    if (!$reqRecord || ($reqRecord['supplier_id'] ?? null) != $supplier_id) {
        $_SESSION['flash'] = 'Request not found or access denied.';
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }

    if ($action === 'set_price') {
        $priceRaw = $_POST['price'] ?? '';
        if (!is_numeric($priceRaw) || floatval($priceRaw) <= 0) {
            $_SESSION['flash'] = 'Enter a valid positive price.';
        } else {
            $price = floatval($priceRaw);
            sb_update_table('material_requests', ['price' => $price, 'status' => 'quoted'], 'id=eq.' . urlencode($req_id));
            $_SESSION['flash'] = 'Price updated.';
        }
    } elseif ($action === 'accept') {
        if (($reqRecord['status'] ?? '') === 'accepted') {
            $_SESSION['flash'] = 'Request already accepted.';
        } else {
            sb_update_table('material_requests', ['status' => 'accepted'], 'id=eq.' . urlencode($req_id));
            $_SESSION['flash'] = 'Request accepted.';
        }
    } elseif ($action === 'reject') {
        if (($reqRecord['status'] ?? '') === 'rejected') {
            $_SESSION['flash'] = 'Request already rejected.';
        } else {
            sb_update_table('material_requests', ['status' => 'rejected'], 'id=eq.' . urlencode($req_id));
            $_SESSION['flash'] = 'Request rejected.';
        }
    } else {
        $_SESSION['flash'] = 'Unknown action.';
    }

    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

// GET: list requests for this supplier
$statusFilter = $_GET['status'] ?? '';
$filterQ = 'supplier_id=eq.' . urlencode($supplier_id);
if ($statusFilter !== '') $filterQ .= '&status=eq.' . urlencode($statusFilter);
$requestsRes = sb_get_table('material_requests', $filterQ . '&order=created_at.desc&select=*');
$requests = [];
// debug: log supplier/filter and primary response
error_log('manage-requests: supplier_id=' . var_export($supplier_id, true) . ' filter=' . $filterQ);
error_log('manage-requests: primary response status=' . ($requestsRes['status'] ?? 'n/a') . ' ok=' . var_export($requestsRes['ok'] ?? null, true));
error_log('manage-requests: primary raw=' . substr($requestsRes['raw'] ?? '', 0, 1000));
if (is_array($requestsRes) && isset($requestsRes['body']) && is_array($requestsRes['body'])) {
    $requests = $requestsRes['body'];
    error_log('manage-requests: primary fetch count=' . count($requests));
} else {
    // attempt anonymous fallback in case service key isn't available to webserver
    $path = '/rest/v1/material_requests?' . ltrim($filterQ . '&order=created_at.desc&select=*', '?');
    $anonRes = sb_request('GET', $path, null, true);
    error_log('manage-requests: anon response status=' . ($anonRes['status'] ?? 'n/a') . ' ok=' . var_export($anonRes['ok'] ?? null, true));
    error_log('manage-requests: anon raw=' . substr($anonRes['raw'] ?? '', 0, 1000));
    if (is_array($anonRes) && isset($anonRes['body']) && is_array($anonRes['body'])) {
        $requests = $anonRes['body'];
        error_log('manage-requests: anon fetch count=' . count($requests));
    } else {
        error_log('manage-requests: anon fallback failed');
        $requests = [];
    }
}

// fetch materials map and project_materials to resolve group_id
$materialsMap = [];
$matRes = sb_get_table('materials', 'select=id,name');
if (is_array($matRes) && isset($matRes['body']) && is_array($matRes['body'])) {
    foreach ($matRes['body'] as $m) {
        if (!is_array($m)) continue;
        if (!empty($m['name'])) $materialsMap[strtolower(trim($m['name']))] = $m['id'];
    }
}

// fetch projects map
$projects = [];
$projectsRes = sb_get_table('projects', 'select=id,name');
if (is_array($projectsRes) && isset($projectsRes['body']) && is_array($projectsRes['body'])) {
    foreach ($projectsRes['body'] as $p) {
        if (!is_array($p)) continue;
        if (isset($p['id'])) $projects[$p['id']] = $p;
    }
}

// annotate group_id for each request
foreach ($requests as &$r) {
    if (!is_array($r)) continue;
    $r['group_id'] = 1;
    $matName = strtolower(trim($r['material'] ?? ''));
    if ($matName !== '' && isset($materialsMap[$matName]) && !empty($r['project_id'])) {
        $mid = $materialsMap[$matName];
        $pmRes = sb_get_table('project_materials', 'project_id=eq.' . urlencode($r['project_id']) . '&material_id=eq.' . urlencode($mid) . '&select=group_id');
        if (is_array($pmRes) && isset($pmRes['body']) && is_array($pmRes['body']) && count($pmRes['body'])>0) {
            $g = $pmRes['body'][0]['group_id'] ?? null;
            if ($g !== null) $r['group_id'] = intval($g);
        }
    }
    $projKey = $r['project_id'] ?? '';
    $projEntry = null;
    if (is_array($projects) && array_key_exists($projKey, $projects) && is_array($projects[$projKey])) {
        $projEntry = $projects[$projKey];
    }
    if (is_array($projEntry)) {
        $r['project_name'] = $projEntry['name'] ?? $projKey;
    } else {
        $r['project_name'] = $projKey;
    }
}
unset($r);

// group
$grouped = [];
foreach ($requests as $r) {
    $pid = $r['project_id'] ?? 0;
    $gid = $r['group_id'] ?? 1;
    if (!is_array($r)) continue;
    if (!isset($grouped[$pid])) $grouped[$pid] = [];
    if (!isset($grouped[$pid][$gid])) $grouped[$pid][$gid] = [];
    $grouped[$pid][$gid][] = $r;
}

// render
ob_start();
?>
<div class="card">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div style="padding:8px;background:#f0f8ff;border:1px solid #cce;margin-bottom:8px"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <div style="margin-bottom:12px">
        <form method="get" style="display:flex;gap:8px;align-items:center">
            <label>Filter:</label>
            <select name="status">
                <option value="">All</option>
                <option value="requested" <?php echo $statusFilter==='requested'?'selected':''; ?>>Requested</option>
                <option value="quoted" <?php echo $statusFilter==='quoted'?'selected':''; ?>>Quoted</option>
                <option value="accepted" <?php echo $statusFilter==='accepted'?'selected':''; ?>>Accepted</option>
                <option value="rejected" <?php echo $statusFilter==='rejected'?'selected':''; ?>>Rejected</option>
            </select>
            <button type="submit">Apply</button>
        </form>
    </div>

    <?php if (empty($grouped)): ?>
        <p>No requests found.</p>
    <?php else: ?>
        <?php foreach ($grouped as $pid => $groups): ?>
            <div style="margin-bottom:14px">
                <?php
                    $projTitle = 'Project ' . $pid;
                    if (isset($projects[$pid]) && is_array($projects[$pid])) {
                        $projTitle = $projects[$pid]['name'] ?? $projTitle;
                    }
                ?>
                <h3 style="margin:6px 0"><?php echo htmlspecialchars($projTitle); ?></h3>
                <?php foreach ($groups as $gid => $items): ?>
                    <div style="margin:8px 0;padding:8px;border:1px dashed #e6eef6;border-radius:8px;background:#fbfdff">
                        <div style="font-weight:600;margin-bottom:8px">Group <?php echo htmlspecialchars($gid); ?></div>
                        <table border="0" cellpadding="8" style="width:100%">
                            <thead><tr><th>Material</th><th>Qty</th><th>Requested By</th><th>Requested At</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $r): ?>
                                <?php if (!is_array($r)) continue; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['material'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($r['quantity'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($r['requested_by'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($r['created_at'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($r['price'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($r['status'] ?? ''); ?></td>
                                    <td>
                                        <?php if (($r['status'] ?? '') === 'requested' || ($r['status'] ?? '') === 'quoted'): ?>
                                            <form method="post" style="display:inline-block;margin-right:6px" onsubmit="return confirm('Accept this request?')">
                                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($r['id']); ?>" />
                                                <input type="hidden" name="action" value="accept" />
                                                <button type="submit">Accept</button>
                                            </form>
                                            <form method="post" style="display:inline-block;margin-right:6px" onsubmit="return confirm('Reject this request?')">
                                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($r['id']); ?>" />
                                                <input type="hidden" name="action" value="reject" />
                                                <button type="submit">Reject</button>
                                            </form>
                                            <form method="post" style="display:inline-block" onsubmit="return validatePrice(this)">
                                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($r['id']); ?>" />
                                                <input type="hidden" name="action" value="set_price" />
                                                <input name="price" placeholder="Price" style="width:80px" />
                                                <button type="submit">Set Price</button>
                                            </form>
                                        <?php else: ?>
                                            <em>No actions</em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
$inner = ob_get_clean();
echo $inner;
