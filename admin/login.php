<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $user = qone("SELECT * FROM users WHERE email = ?", [$email]);
        if ($user && password_verify($password, $user['password_hash'])) {
            if (in_array($user['role'], ['admin','staff'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Access denied: not an admin/staff account.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login - VisionDrive</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/style.css">
<style>
body{display:flex;align-items:center;justify-content:center;height:100vh;background:#0b0f16;color:#fff;font-family:system-ui}
.login-box{background:#121826;padding:40px;border-radius:14px;width:100%;max-width:360px;box-shadow:0 0 12px rgba(0,0,0,.4)}
h2{text-align:center;margin-bottom:24px}
input{width:100%;padding:12px;border:none;border-radius:8px;margin-bottom:16px;background:#182235;color:#fff}
button{width:100%;padding:12px;border:none;border-radius:8px;background:#4f7cff;color:#fff;font-weight:600;cursor:pointer}
button:hover{filter:brightness(1.1)}
.error{color:#ff6b6b;text-align:center;margin-bottom:12px}
</style>
</head>
<body>
<div class="login-box">
  <h2>Admin Login</h2>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>
