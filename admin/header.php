<?php
require_once 'auth.php';
require_once __DIR__ . '/../includes/db.php';
if (!isset($PAGE_TITLE)) $PAGE_TITLE = 'Admin';
if (!isset($BREADCRUMB)) $BREADCRUMB = 'Admin';
$current = basename($_SERVER['PHP_SELF']);
function active($f){global $current;return $current===$f?'active':'';}

/* Avatar: lấy ký tự đầu của tên (UTF-8, có fallback nếu thiếu mbstring) */
$displayName = trim($_SESSION['user_name'] ?? 'Admin');
if ($displayName === '') $displayName = 'Admin';
if (function_exists('mb_substr')) {
  $initial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');
} else {
  $initial = strtoupper(substr($displayName, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>VisionDrive Admin — <?= htmlspecialchars($PAGE_TITLE) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/admin/assets/style.css?v=5">
</head>
<body class="light">
<div class="layout">
  <aside class="sidebar">
    <div class="brand">VisionDrive <span class="badge">Admin</span></div>
    <nav class="menu">
      <a class="<?= active('dashboard.php') ?>" href="dashboard.php" title="Dashboard">
        <span class="icon" aria-hidden="true">🏠</span><span class="text">Dashboard</span>
      </a>
      <a class="<?= active('users.php') ?>" href="users.php" title="Manage Users">
        <span class="icon" aria-hidden="true">👤</span><span class="text">Manage Users</span>
      </a>
      <a class="<?= active('courses.php') ?>" href="courses.php" title="Manage Courses">
        <span class="icon" aria-hidden="true">📚</span><span class="text">Manage Courses</span>
      </a>
      <a class="<?= active('bookings.php') ?>" href="bookings.php" title="Manage Bookings">
        <span class="icon" aria-hidden="true">🗂</span><span class="text">Manage Bookings</span>
      </a>
      <a class="<?= active('campuses.php') ?>" href="campuses.php" title="Manage Campuses">
        <span class="icon" aria-hidden="true">🏫</span><span class="text">Manage Campuses</span>
      </a>
      <a class="<?= active('scheduled.php') ?>" href="scheduled.php" title="Scheduled">
        <span class="icon" aria-hidden="true">🗓</span><span class="text">Scheduled</span>
      </a>
    </nav>
  </aside>

  <!-- Backdrop for mobile (only one) -->
  <div class="sidebar-backdrop" aria-hidden="true"></div>

  <div class="main">
    <header class="topbar">
      <!-- Hamburger -->
      <button
        id="sidebarToggle"
        class="btn-toggle"
        aria-label="Toggle navigation"
        aria-expanded="false"
        onclick="(function(btn){
          var body=document.body;
          var sidebar=document.querySelector('.sidebar');
          var backdrop=document.querySelector('.sidebar-backdrop');
          if(!sidebar){return;}
          var isMobile=window.matchMedia('(max-width:1024px)').matches;
          function setExpanded(on){ try{ btn.setAttribute('aria-expanded', on?'true':'false'); }catch(e){} }
          if(isMobile){
            var opened=sidebar.classList.contains('open')||sidebar.classList.contains('show');
            if(opened){
              sidebar.classList.remove('open'); sidebar.classList.remove('show'); setExpanded(false);
              if(backdrop){ backdrop.style.display='none'; }
            }else{
              sidebar.classList.add('open'); sidebar.classList.add('show'); setExpanded(true);
              if(backdrop){ backdrop.style.display='block'; }
            }
          }else{
            var collapsed=body.classList.toggle('sidebar-collapsed');
            setExpanded(collapsed);
          }
        })(this)">
        ☰
      </button>

      <div class="topbar-right">
        <span class="divider"></span>

        <!-- Avatar tròn, chỉ chữ cái đầu (màu chữ hơi nhạt) -->
        <a href="profile.php" class="user-avatar" title="<?= htmlspecialchars($displayName) ?>" aria-label="Open profile">
          <span class="avatar" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
        </a>

        <a href="logout.php" class="btn btn-logout fw-semibold px-3 text-white">Logout</a>
      </div>
    </header>

    <div class="page-header">
      <div class="breadcrumb"><?= htmlspecialchars($BREADCRUMB) ?></div>
      <h1 class="page-title"><?= htmlspecialchars($PAGE_TITLE) ?></h1>
    </div>

    <!-- CONTENT START -->
    <main class="content">
