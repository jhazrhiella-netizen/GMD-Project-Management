<?php
require_once __DIR__ . '/../../config.php';
require_login();

$flash = '';
$id = $_GET['id'] ?? null;

if ($id) {
    // handle update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
        // verify CSRF token
        $token = $_POST['_csrf'] ?? '';
        if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
            $flash = 'Invalid or expired form submission (CSRF).';
        } else {
            $name = trim($_POST['name'] ?? '');
            $client = trim($_POST['client'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $start_date = trim($_POST['start_date'] ?? null);
            $end_date = trim($_POST['end_date'] ?? null);
            $progress = floatval($_POST['progress'] ?? 0);

            if ($name === '') {
                $flash = 'Project name is required.';
            } else {
                $data = [
                    'name' => $name,
                    'client' => $client,
                    'status' => $status,
                    'description' => $description,
                    'start_date' => $start_date ?: null,
                    'end_date' => $end_date ?: null,
                    'progress' => $progress
                ];
                sb_update_table('projects', $data, 'id=eq.' . urlencode($id));
                $flash = 'Project updated.';
            }
        }
    }

    $project = null;
    $res = sb_get_table('projects', 'id=eq.' . urlencode($id));
    if (isset($res['body'][0])) $project = $res['body'][0];
} else {
    $project = null;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Project Details</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <style>
    .progress-bar { background:#eee; border-radius:4px; overflow:hidden; height:14px; }
    .progress-fill { height:100%; background:#4caf50; text-align:center; color:#fff; font-size:12px }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../admin-pages/header.php'; ?>
    <div class="app-container">
        <?php include __DIR__ . '/../../admin-pages/sidebar.php'; ?>
        <div class="app-main">
            <h2>Project Details</h2>
            <div class="card">
                <?php if (!empty($flash)): ?>
                    <div style="padding:8px;background:#f6ffef;border:1px solid #cfc;margin-bottom:8px"><?php echo htmlspecialchars($flash); ?></div>
                <?php endif; ?>
                <?php if (!$project): ?>
                    <p>No project selected. Pass ?id=&lt;project-id&gt; in the URL.</p>
                <?php else: ?>
                    <?php $isEdit = isset($_GET['edit']) && $_GET['edit']=='1'; ?>
                    <?php if ($isEdit): ?>
                        <form method="post">
                            <div style="display:flex;flex-direction:column;gap:8px;max-width:800px">
                                <input name="name" value="<?php echo htmlspecialchars($project['name'] ?? ''); ?>" placeholder="Project name" />
                                <input name="client" value="<?php echo htmlspecialchars($project['client'] ?? ''); ?>" placeholder="Client" />
                                <input name="status" value="<?php echo htmlspecialchars($project['status'] ?? ''); ?>" placeholder="Status" />
                                <label>Description</label>
                                <textarea name="description" rows="6"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                                <div style="display:flex;gap:8px;align-items:center">
                                    <label>Start</label>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>" />
                                    <label>End</label>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($project['end_date'] ?? ''); ?>" />
                                    <label>Progress %</label>
                                    <input type="number" name="progress" value="<?php echo htmlspecialchars($project['progress'] ?? 0); ?>" style="width:100px" />
                                </div>
                                <input type="hidden" name="action" value="save" />
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                                <div>
                                    <button type="submit">Save</button>
                                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . urlencode($id)); ?>">Cancel</a>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <h3><?php echo htmlspecialchars($project['name'] ?? ''); ?></h3>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars($project['client'] ?? ''); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($project['status'] ?? ''); ?></p>
                        <p><strong>Start:</strong> <?php echo htmlspecialchars($project['start_date'] ?? ''); ?> &nbsp; <strong>End:</strong> <?php echo htmlspecialchars($project['end_date'] ?? ''); ?></p>
                        <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?></p>
                        <p><strong>Progress:</strong></p>
                        <?php $prog = floatval($project['progress'] ?? 0); if ($prog < 0) $prog = 0; if ($prog > 100) $prog = 100; ?>
                        <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $prog; ?>%"><?php echo $prog; ?>%</div></div>
                        <div style="margin-top:8px"><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . urlencode($id) . '&edit=1'); ?>">Edit Project</a></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
This module will be used in the project-view.php. It now supports editing basic project fields, displays start/end dates and a progress bar, and includes server-side validation for the project name.