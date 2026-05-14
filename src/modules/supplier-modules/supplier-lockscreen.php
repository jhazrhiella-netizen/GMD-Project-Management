<?php
require_once __DIR__ . '/../../config.php';
// supplier lockscreen: set a session flag when action=lock, require password to unlock
require_login();
$user = get_current_user();

if (isset($_GET['action']) && $_GET['action']==='lock') {
    $_SESSION['supplier_locked'] = true;
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        $error = 'Invalid form submission.';
    } else {
        $password = $_POST['password'] ?? '';
        if (!$password) {
            $error = 'Enter your password to unlock.';
        } else {
            // try to sign in to verify credentials
            $signin = sb_sign_in($user['email'] ?? '', $password);
            if (!empty($signin['error']) || (($signin['status'] ?? 0) >= 400)) {
                $error = 'Unlock failed. Incorrect password.';
            } else {
                // success: clear lock
                unset($_SESSION['supplier_locked']);
                header('Location: /src/supplier-pages/supplier-dashboard.php');
                exit;
            }
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Locked</title>
    <link rel="stylesheet" href="/src/css/styles.css">
    <style>
    .locked-wrap { display:flex;align-items:center;justify-content:center;height:100vh }
    .locked-card { border:1px solid #ddd;padding:20px;border-radius:6px;background:#fff;width:360px }
    </style>
</head>
<body>
    <?php if (empty($_SESSION['supplier_locked'])): ?>
        <div style="max-width:1100px;margin:16px auto">
            <div class="card">
                <p>Screen is not locked. <a href="?action=lock">Lock now</a></p>
            </div>
        </div>
    <?php else: ?>
        <div class="locked-wrap">
            <div class="locked-card">
                <h3>Screen Locked</h3>
                <p>Enter your password to unlock.</p>
                <?php if ($error): ?><div style="color:#a00;margin-bottom:8px"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>" />
                    <input type="password" name="password" placeholder="Password" style="width:100%;padding:8px;margin-bottom:8px" />
                    <button type="submit">Unlock</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
A modal form that will pop up when the supplier needs to lock the screen. It will require the supplier to enter their credentials to unlock the screen.