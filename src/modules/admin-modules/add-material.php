<?php
require_once __DIR__ . '/../../config.php';
require_login();

$embed = isset($_REQUEST['embed']) && $_REQUEST['embed'] == '1';
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : (isset($_POST['project_id']) ? intval($_POST['project_id']) : 0);
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_selected'])) {
    $token = $_POST['_csrf'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        $flash = 'Invalid form submission.';
    } else {
        $selected = $_POST['materials'] ?? [];
        if (!$project_id) {
            $flash = 'No project specified.';
        } elseif (empty($selected) || !is_array($selected)) {
            $flash = 'No materials selected.';
        } else {
            $added = 0;
            $errors = [];

            // compute next group id for this project (max existing group_id + 1)
            $maxGroup = 0;
            $pmGroupRes = sb_get_table('project_materials', 'project_id=eq.' . urlencode($project_id) . '&select=group_id');
            if (isset($pmGroupRes['body']) && is_array($pmGroupRes['body'])) {
                foreach ($pmGroupRes['body'] as $r) {
                    $g = isset($r['group_id']) ? intval($r['group_id']) : 0;
                    if ($g > $maxGroup) $maxGroup = $g;
                }
            }
            $nextGroup = $maxGroup + 1;

            foreach ($selected as $mid) {
                $mid = intval($mid);
                if (!$mid) continue;
                $check = sb_get_table('project_materials', 'project_id=eq.' . urlencode($project_id) . '&material_id=eq.' . urlencode($mid));
                $exists = isset($check['body']) && is_array($check['body']) && count($check['body'])>0;
                if ($exists) continue;
                $mRes = sb_get_table('materials', 'id=eq.' . urlencode($mid));
                $m = isset($mRes['body'][0]) ? $mRes['body'][0] : null;
                $unit_price = $m['unit_price'] ?? 0;
                $ins = ['project_id' => $project_id, 'material_id' => $mid, 'quantity' => 0, 'unit_price' => floatval($unit_price), 'group_id' => $nextGroup];
                $res = sb_insert_table('project_materials', $ins);
                if (isset($res['ok']) && $res['ok']) {
                    $added++;
                } else {
                    $errors[] = ['material_id' => $mid, 'res' => $res];
                }
            }

            $flash = $added . ' materials added to project.';
            if (!empty($errors)) {
                $flash .= ' Some inserts failed.';
                if ($embed) {
                    $flash .= ' Errors: ' . htmlspecialchars(json_encode($errors));
                }
            }
        }
    }
}

$materialsRes = sb_get_table('materials');
$materials = $materialsRes['body'] ?? [];

// fetch existing project materials to mark checked/disabled
$existing = [];
if ($project_id) {
    $pmRes = sb_get_table('project_materials', 'project_id=eq.' . urlencode($project_id));
    if (isset($pmRes['body']) && is_array($pmRes['body'])) {
        foreach ($pmRes['body'] as $p) $existing[intval($p['material_id'])] = $p;
    }
}

// If embed mode is not requested, output full page wrapper
if (!$embed) {
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Add Materials</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <style>
    .materials-table { width:100%; border-collapse:collapse }
    .materials-table th, .materials-table td { border:1px solid #ddd; padding:8px }
    </style>
</head>
<body>
    <div class="module-container">
        <h2>Select Materials</h2>
        <div class="card">
<?php
}

// common inner content (works for both embed and full page)
?>
            <?php if ($flash): ?><div style="padding:8px;background:#f6ffef;border:1px solid #cfc;margin-bottom:8px"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
            <?php if (!$project_id): ?>
                <div class="card">No project specified. Provide <strong>project_id</strong> in the URL.</div>
            <?php else: ?>
                <form method="post" action="/src/modules/admin-modules/add-material.php<?php echo $embed? '?embed=1' : ''; ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>" />
                    <?php if ($embed): ?>
                    <table class="materials-table">
                        <thead><tr><th></th><th>Name</th></tr></thead>
                        <tbody>
                        <?php foreach($materials as $m): ?>
                            <tr>
                                <td>
                                    <?php $mid = intval($m['id'] ?? 0); $already = isset($existing[$mid]); ?>
                                    <input type="checkbox" name="materials[]" value="<?php echo htmlspecialchars($mid); ?>" <?php echo $already? 'disabled' : ''; ?> />
                                </td>
                                <td><?php echo htmlspecialchars($m['name'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <table class="materials-table">
                        <thead><tr><th></th><th>Name</th><th>Unit Price</th><th>Available Qty</th></tr></thead>
                        <tbody>
                        <?php foreach($materials as $m): ?>
                            <tr>
                                <td>
                                    <?php $mid = intval($m['id'] ?? 0); $already = isset($existing[$mid]); ?>
                                    <input type="checkbox" name="materials[]" value="<?php echo htmlspecialchars($mid); ?>" <?php echo $already? 'disabled' : ''; ?> />
                                </td>
                                <td><?php echo htmlspecialchars($m['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(number_format(floatval($m['unit_price'] ?? 0),2)); ?></td>
                                <td><?php echo htmlspecialchars($m['quantity'] ?? '0'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <div style="margin-top:8px">
                        <button type="submit" name="add_selected">Add Selected</button>
                        <?php if (!$embed): ?>
                            <a href="/src/modules/admin-modules/materials-list.php?project_id=<?php echo htmlspecialchars($project_id); ?>">Back to Materials List</a>
                        <?php else: ?>
                            <button type="button" class="modal-close" onclick="document.getElementById('materials-modal').classList.remove('open')">Close</button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
<?php
if (!$embed) {
?>
        </div>
    </div>
</body>
</html>
<?php
}

