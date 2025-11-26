<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Chỉ coi là login khi role thuộc các giá trị hợp lệ */
$role = $_SESSION['role'] ?? '';
$validRoles = ['student','staff','admin'];
$isLoggedIn = in_array($role, $validRoles, true);

$fullName = $isLoggedIn ? trim($_SESSION['full_name'] ?? '') : '';
$email    = $isLoggedIn ? trim($_SESSION['email'] ?? '')      : '';
$display  = $fullName !== '' ? $fullName : ($email !== '' ? explode('@', $email)[0] : '');
$initial  = $display !== '' ? mb_strtoupper(mb_substr($display, 0, 1, 'UTF-8'), 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VisionDrive Training</title>
<link rel="stylesheet" href="userassets/style_user.css?v=2">
<!-- optional: giảm cache header -->
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
</head>
<body class="vd-body">

<header class="site-header" role="banner">
  <div class="site-header-inner">
    <!-- Logo góc trái -->
    <div class="brand">
      <a href="/user/index.php" class="brand-link" aria-label="VisionDrive home">
        <img src="userassets/Logo.png?v=2" alt="VisionDrive logo" class="brand-logo-full">
      </a>
    </div>

    <!-- Nav chính giữa -->
    <nav class="main-nav" role="navigation" aria-label="Primary">
      <a href="/user/index.php" class="nav-link">Home</a>
      <a href="/user/my_courses.php" class="nav-link">My course</a>
      <a href="#" class="nav-link">Courses</a>
      <a href="#" class="nav-link">Contact us</a>
      <a href="#" class="nav-link">Policy</a>
    </nav>

    <!-- User actions góc phải -->
    <div class="user-actions">
      <a href="/user/booking_type.php" class="btn-book-now">Book now</a>

      <?php if ($isLoggedIn): ?>
        <?php if ($initial !== ''): ?>
          <div class="user-chip" title="<?= htmlspecialchars($display) ?>">
            <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
          </div>
        <?php endif; ?>
        <a href="/user/logout.php" class="btn-auth btn-logout" aria-label="Logout">Logout</a>
      <?php else: ?>
        <a href="/user/login.php" class="btn-auth btn-login" aria-label="Login">Login</a>
      <?php endif; ?>
    </div>
  </div>
</header>
