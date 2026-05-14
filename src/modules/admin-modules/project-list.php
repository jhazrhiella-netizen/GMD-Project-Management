<?php
require_once __DIR__ . '/../../config.php';
require_login();

// Fetch projects (example) - expects a `projects` table in supabase
$projectsRes = sb_get_table('projects');
$rows = [];
if (isset($projectsRes['body']) && is_array($projectsRes['body'])) $rows = $projectsRes['body'];

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $filtered = [];
    foreach ($rows as $r) {
        if (stripos($r['name'] ?? '', $q) !== false || stripos($r['description'] ?? '', $q) !== false) $filtered[] = $r;
    }
    $rows = $filtered;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Projects</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <script src="/src/js/main.js" defer></script>
</head>
<body>
    <?php include __DIR__ . '/../../admin-pages/header.php'; ?>
    <div class="app-container">
        <?php include __DIR__ . '/../../admin-pages/sidebar.php'; ?>
        <div class="app-main">
            <h2>Projects</h2>
            <div class="card">
                <form method="get" style="margin-bottom:12px;display:flex;gap:8px;align-items:center">
                    <input name="q" placeholder="Search projects" value="<?php echo htmlspecialchars($q); ?>" />
                    <button type="submit">Search</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Clear</a>
                </form>

                <?php if (empty($rows)): ?>
                    <p>No projects found. Create some in Supabase `projects` table.</p>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">
                        <?php foreach($rows as $r): ?>
                            <div style="border:1px solid #ddd;padding:12px;border-radius:6px;background:#fff">
                                <h3 style="margin:0 0 8px"><a href="/src/modules/admin-modules/project-details.php?id=<?php echo urlencode($r['id']); ?>"><?php echo htmlspecialchars($r['name'] ?? '[no name]'); ?></a></h3>
                                <p style="margin:0 0 6px"><strong>Client:</strong> <?php echo htmlspecialchars($r['client'] ?? ''); ?></p>
                                <p style="margin:0 0 6px"><strong>Status:</strong> <?php echo htmlspecialchars($r['status'] ?? ''); ?></p>
                                <p style="margin:0 0 8px;color:#555;font-size:14px"><?php echo htmlspecialchars(mb_strimwidth($r['description'] ?? '', 0, 180, '...')); ?></p>
                                <div><a href="/src/modules/admin-modules/project-details.php?id=<?php echo urlencode($r['id']); ?>">View Details</a></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
This will be used in the project.php. This module is a container that will list all the projects in the database. My idea on this is a card layout where each card will represent a project. The card will show the project name, description, start date, and lead time. The admin can click on the card to view the project details in the project-view.php. This will make it easier for the admin to quickly browse through the projects and find the one they are looking for. The card layout will also make it visually appealing and organized.