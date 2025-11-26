<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// must come from completed booking flow
if (empty($_SESSION['just_booked_name'])) {
    header("Location: /user/course_selection.php");
    exit;
}

$first_name = $_SESSION['just_booked_name'];
unset($_SESSION['just_booked_name']);

require_once __DIR__ . '/header_user.php';
?>
<section class="step-wrapper">
  <h1 class="step-title">Thanks, <?php echo htmlspecialchars($first_name); ?>!</h1>
  <p class="step-hint">
    Your booking has been received. We’ll be in touch with next steps by email.
  </p>

  <div class="calendar-actions" style="justify-content:center">
    <a class="btn-primary" href="/user/course_selection.php">Book another course</a>
  </div>
</section>
<?php require_once __DIR__ . '/footer_user.php'; ?>
