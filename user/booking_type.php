<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// reset flow session when landing here
unset($_SESSION['course_id'], $_SESSION['campus_id'], $_SESSION['offering_id'], $_SESSION['training_date']);

$prefillType  = $_GET['type'] ?? '';
$prefillId    = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$prefillName  = $_GET['course_name'] ?? '';
$isIndividual = (strtolower($prefillType) === 'individual');

// build query string để chuyển tiếp qua course_selection
$carry = [];
if ($prefillId > 0)   $carry['course_id'] = $prefillId;
if ($prefillName !== '') $carry['course_name'] = $prefillName;
$qs = http_build_query($carry);

require_once __DIR__ . '/header_user.php';
?>

<section class="step-wrapper">
  <h1 class="step-title">Who is this booking for?</h1>

  <div class="booking-type-grid">
    <div class="type-card" style="<?php echo $isIndividual ? 'outline:2px solid var(--bg-accent);' : '' ?>">
      <div class="type-card-body">
        <h2 class="type-title">Individual</h2>
        <p class="type-desc">For personal registration. Pay as an individual learner.</p>
        <a class="btn-primary stretch" href="/user/course_selection.php<?php echo $qs ? ('?'.$qs) : ''; ?>" aria-label="Book as individual">
          <?php echo $isIndividual ? 'Selected' : 'Select'; ?>
        </a>
      </div>
    </div>

    <div class="type-card disabled">
      <div class="type-card-body">
        <h2 class="type-title">Company</h2>
        <p class="type-desc">For organisations booking staff. Invoice available.</p>
        <button class="btn-disabled stretch" disabled aria-disabled="true">
          Coming soon
        </button>
      </div>
    </div>
  </div>
</section>

<?php
require_once __DIR__ . '/footer_user.php';
