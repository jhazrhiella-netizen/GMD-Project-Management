<?php
require_once __DIR__ . '/../../config.php';
require_login();

$flash = '';
// Handle add / save / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        $flash = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $quantity = $_POST['quantity'] ?? '';
        $unit_price = $_POST['unit_price'] ?? '';
        if ($name === '' || !is_numeric($quantity) || !is_numeric($unit_price) || floatval($quantity) < 0 || floatval($unit_price) < 0) {
            $flash = 'Invalid input. Ensure name and non-negative numeric quantity and price.';
        } else {
            $data = ['name' => $name, 'quantity' => floatval($quantity), 'unit_price' => floatval($unit_price)];
            sb_insert_table('materials', $data);
            $flash = 'Material added.';
        }
    } elseif ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $quantity = $_POST['quantity'] ?? '';
        $unit_price = $_POST['unit_price'] ?? '';
        if (!$id || $name === '' || !is_numeric($quantity) || !is_numeric($unit_price) || floatval($quantity) < 0 || floatval($unit_price) < 0) {
            $flash = 'Invalid input for update.';
        } else {
            sb_update_table('materials', ['name' => $name, 'quantity' => floatval($quantity), 'unit_price' => floatval($unit_price)], 'id=eq.' . $id);
            $flash = 'Material updated.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            sb_delete_table('materials', 'id=eq.' . $id);
            $flash = 'Material deleted.';
        }
    }
    }
}

$materialsRes = sb_get_table('materials');
$rows = [];
if (isset($materialsRes['body']) && is_array($materialsRes['body'])) $rows = $materialsRes['body'];

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
    <?php include __DIR__ . '/../../admin-pages/header.php'; ?>
    <div class="app-container">
        <?php include __DIR__ . '/../../admin-pages/sidebar.php'; ?>
        <div class="app-main">
            <h2>Materials</h2>
            <div class="card">
                <?php if ($flash): ?><div style="padding:8px;background:#f6ffef;border:1px solid #cfc;margin-bottom:8px"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>

                <h3>Add Material</h3>
                <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                    <input name="name" placeholder="Material name" />
                    <input name="quantity" placeholder="Quantity" style="width:100px" />
                    <input name="unit_price" placeholder="Unit price" style="width:120px" />
                    <input type="hidden" name="action" value="add" />
                    <button type="submit">Add</button>
                </form>

                <?php if (empty($rows)): ?>
                    <p>No materials found. Add materials using the form above.</p>
                <?php else: ?>
                    <table class="materials-table">
                        <thead><tr><th>Name</th><th>Quantity</th><th>Unit Price</th><th>Total Price</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach($rows as $m):
                            $total = (floatval($m['quantity'] ?? 0) * floatval($m['unit_price'] ?? 0));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($m['quantity'] ?? '0'); ?></td>
                                <td><?php echo htmlspecialchars(number_format(floatval($m['unit_price'] ?? 0),2)); ?></td>
                                <td><?php echo htmlspecialchars(number_format($total,2)); ?></td>
                                <td class="materials-actions">
                                    <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                        <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($m['id']); ?>" />
                                        <button type="submit">Edit</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete material?')">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($m['id']); ?>" />
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                                        <input type="hidden" name="action" value="delete" />
                                        <button type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (isset($_GET['edit_id'])):
                    $editId = intval($_GET['edit_id']);
                    $editRow = null;
                    foreach ($rows as $rr) if (($rr['id'] ?? 0) == $editId) { $editRow = $rr; break; }
                    if ($editRow): ?>
                        <hr />
                        <h3>Edit Material</h3>
                        <form method="post" style="display:flex;gap:8px;align-items:center;margin-top:8px">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                            <input name="name" value="<?php echo htmlspecialchars($editRow['name'] ?? ''); ?>" />
                            <input name="quantity" value="<?php echo htmlspecialchars($editRow['quantity'] ?? '0'); ?>" style="width:100px" />
                            <input name="unit_price" value="<?php echo htmlspecialchars($editRow['unit_price'] ?? '0'); ?>" style="width:120px" />
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editRow['id']); ?>" />
                            <input type="hidden" name="action" value="save" />
                            <button type="submit">Save</button>
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Cancel</a>
                        </form>
                    <?php endif; endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
This module will be used in the project-view.php. This module will be used to display the materials that will be used in the project. The materials are shown in a table with unit price and a computed total price. Basic add/edit/delete CRUD is provided.