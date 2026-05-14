<?php
require_once __DIR__ . '/../../config.php';
require_login();

$currentUser = get_current_user();
$supplier_id = $currentUser['id'] ?? null;

// detect embed mode: either via defined constant EMBED or GET param embed=1
$isEmbed = (defined('EMBED') && EMBED) || (isset($_GET['embed']) && $_GET['embed']=='1');

// Handle actions: set price / accept / reject with server-side validation and ownership check
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
    // fetch the request and verify it belongs to this supplier
    $checkRes = sb_get_table('material_requests', 'id=eq.' . urlencode($req_id));
    $reqRecord = $checkRes['body'][0] ?? null;
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

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch requests for this supplier
$statusFilter = $_GET['status'] ?? '';
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$baseFilter = 'supplier_id=eq.' . urlencode($supplier_id);
if ($statusFilter) $baseFilter .= '&status=eq.' . urlencode($statusFilter);

// total count: use PostgREST exact count via Prefer header when available
$countRes = sb_get_table('material_requests', $baseFilter, ['Prefer: count=exact']);
$totalCount = null;
if (isset($countRes['headers']['content-range'])) {
    $cr = $countRes['headers']['content-range']; // format: 0-9/123
    if (strpos($cr, '/') !== false) {
        $parts = explode('/', $cr);
        $totalCount = intval($parts[1]);
    }
}
if ($totalCount === null) {
    $totalCount = is_array($countRes['body']) ? count($countRes['body']) : 0;
}

// fetch only current page
$pageQuery = $baseFilter . '&order=created_at.desc&limit=' . $perPage . '&offset=' . $offset;
$requestsRes = sb_get_table('material_requests', $pageQuery);
$requests = $requestsRes['body'] ?? [];

// Fetch projects to resolve project names
$projectsRes = sb_get_table('projects');
$projects = [];
if (isset($projectsRes['body']) && is_array($projectsRes['body'])) {
    foreach ($projectsRes['body'] as $p) {
        if (isset($p['id'])) $projects[$p['id']] = $p;
    }
}

// Prepare inner HTML
ob_start();
?>
<div class="card">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div style="padding:8px;background:#f0f8ff;border:1px solid #cce;margin-bottom:8px"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
    <?php if (empty($requests)): ?>
        <p>No requests found.</p>
    <?php else: ?>
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
        <table border="0" cellpadding="8">
            <thead><tr><th>Project</th><th>Material</th><th>Qty</th><th>Requested By</th><th>Requested At</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($requests as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($projects[$r['project_id']]['name'] ?? $r['project_id'] ?? ''); ?></td>
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
        <?php
        $totalPages = $perPage > 0 ? intval(ceil($totalCount / $perPage)) : 1;
        if ($totalPages > 1):
            $baseParams = [];
            if ($statusFilter) $baseParams['status'] = $statusFilter;
        ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $page - 1])); ?>">&laquo; Prev</a>
            <?php endif; ?>
            <span>Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $page + 1])); ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$inner = ob_get_clean();
if ($isEmbed) {
    echo $inner;
    ?>
    <script>
    function validatePrice(form){
        var v = form.price.value.trim();
        if (!v || isNaN(v) || Number(v) <= 0){
            alert('Enter a valid price');
            return false;
        }
        return confirm('Set price to ' + v + '?');
    }
    </script>
    <?php
    return;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Requests</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <style>.app-main{margin-left:240px}</style>
</head>
<body>
    <?php include __DIR__ . '/../../admin-pages/header.php'; ?>
    <div class="app-container">
        <?php include __DIR__ . '/../../admin-pages/sidebar.php'; ?>
        <div class="app-main">
            <h2>Material Requests</h2>
            <?php echo $inner; ?>
        </div>
    </div>
</body>
</html>
<script>
function validatePrice(form){
    var v = form.price.value.trim();
    if (!v || isNaN(v) || Number(v) <= 0){
        alert('Enter a valid price');
        return false;
    }
    return confirm('Set price to ' + v + '?');
}
</script>
