<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Nếu đã đăng nhập student → đi tiếp (nếu có pending booking thì để details.php xử lý skip)
if (!empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'student')) {
  if (!empty($_SESSION['offering_id']) && !empty($_SESSION['training_date'])) {
    header('Location: /user/details.php'); // details.php sẽ auto-book khi đã login
  } else {
    header('Location: /user/my_courses.php');
  }
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($email && $password) {
    $user = qone("SELECT * FROM users WHERE email = ?", [$email]);
    if ($user && password_verify($password, $user['password_hash'])) {
      if ($user['role'] === 'student') {
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['role']      = 'student';
        $_SESSION['email']     = $user['email'];

        if (!empty($_SESSION['offering_id']) && !empty($_SESSION['training_date'])) {
          header('Location: /user/details.php'); // skip form khi đã login
        } else {
          header('Location: /user/my_courses.php');
        }
        exit;
      } else {
        $error = "Access denied: not a student account.";
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
<title>Student Login - VisionDrive</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/userassets/style_user.css">
<style>
  :root{
    --bg:#ffffff; --text:#111827; --muted:#6b7280;
    --primary:#0d6efd; --ring:rgba(13,110,253,.15);
    --border:#e5e7eb; --card:#ffffff; --shadow:0 8px 24px rgba(0,0,0,.06);
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
  main{display:flex;justify-content:center;padding:32px 16px}
  .login-wrap{width:100%;max-width:420px}
  .card{
    background:var(--card); border:1px solid var(--border); border-radius:16px;
    box-shadow:var(--shadow); padding:28px;
  }
  h2{margin:0 0 6px; font-size:22px; font-weight:800; text-align:center}
  .muted{color:var(--muted); font-size:14px; text-align:center; margin-bottom:18px}
  .field{margin-bottom:14px}
  .field label{display:block; font-size:13px; margin-bottom:6px; color:#374151}
  .input{
    width:100%; padding:12px; border:1px solid var(--border); border-radius:12px;
    background:#fff; color:var(--text); outline:none; font-size:15px;
  }
  .input:focus{border-color:var(--primary); box-shadow:0 0 0 4px var(--ring)}
  .btn{
    width:100%; padding:12px; border:none; border-radius:12px; cursor:pointer;
    background:var(--primary); color:#fff; font-weight:700; font-size:15px;
  }
  .btn:hover{filter:brightness(1.04)}
  .error{
    color:#b91c1c; background:#fee2e2; border:1px solid #fecaca;
    padding:10px 12px; border-radius:12px; font-size:14px; margin-bottom:12px; text-align:center;
  }
  .links{margin-top:12px; text-align:center; font-size:14px}
  .links a{color:#334155; text-decoration:none}
  .links a:hover{text-decoration:underline}
</style>
</head>
<body>

<?php include __DIR__ . '/header_user.php'; ?>

<main>
  <div class="login-wrap">
    <div class="card">
      <h2>Student Login</h2>
      <div class="muted">Sign in to book and view your courses</div>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="field">
          <label>Email</label>
          <input class="input" type="email" name="email" placeholder="you@example.com" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input class="input" type="password" name="password" placeholder="••••••••" required>
        </div>
        <button class="btn" type="submit">Sign in</button>
      </form>

      <div class="links">
        <a href="/user/index.php">Back to home</a>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer_user.php'; ?>

</body>
</html>
