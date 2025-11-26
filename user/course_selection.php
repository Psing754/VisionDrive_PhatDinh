<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

// Prefill when coming from Home -> course_selection
$prefillCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Fetch active courses
$stmt = $pdo->prepare("SELECT id, name FROM courses WHERE status='active' ORDER BY name ASC");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine if we should LOCK the course selector
// Lock only if the prefilled course actually exists in the list of active courses
$activeIds = array_map(fn($c) => (int)$c['id'], $courses);
$lockedCourseId = ($prefillCourseId && in_array($prefillCourseId, $activeIds, true)) ? $prefillCourseId : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If locked, override whatever was posted to prevent tampering
    $course_id = $lockedCourseId ?: (int)($_POST['course_id'] ?? 0);
    $campus_id = (int)($_POST['campus_id'] ?? 0);

    if (!$course_id) $errors['course_id'] = "Please select a Course.";
    if (!$campus_id) $errors['campus_id'] = "Please select a Campus.";

    if (empty($errors)) {
        $_SESSION['course_id'] = $course_id;
        $_SESSION['campus_id'] = $campus_id;
        header("Location: /user/calendar.php");
        exit;
    }
}

require_once __DIR__ . '/header_user.php';
?>

<section class="step-wrapper">
  <h1 class="step-title">Select course &amp; campus</h1>
  <p class="step-hint">All fields marked <span class="req">*</span> are required</p>

  <?php if (!empty($errors)) : ?>
    <div class="form-error" role="alert">Please fix the highlighted fields.</div>
  <?php endif; ?>

  <form class="vd-form" method="post" action="/user/course_selection.php" novalidate aria-labelledby="courseCampusHeading">
    <div class="form-group">
      <label for="course_id">
        <span class="req">*</span> Select course
        <?php if ($lockedCourseId): ?>
        <?php endif; ?>
      </label>

      <select
        id="course_id"
        name="course_id"
        aria-label="Select course"
        class="<?php echo isset($errors['course_id']) ? 'input-error' : ''; ?>"
        required>
        <option value="">Choose a Course…</option>
        <?php foreach ($courses as $c):
          $cid = (int)$c['id'];

          // selected logic: prefer POST, otherwise use locked prefill
          $selected = (isset($_POST['course_id']) && (int)$_POST['course_id'] === $cid)
                      || (!isset($_POST['course_id']) && $lockedCourseId === $cid);

          // if locked, disable every option that isn't the locked one
          $disabled = $lockedCourseId && $cid !== $lockedCourseId ? 'disabled' : '';
        ?>
          <option
            value="<?php echo $cid; ?>"
            <?php echo $selected ? 'selected' : ''; ?>
            <?php echo $disabled; ?>>
            <?php echo htmlspecialchars($c['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if ($lockedCourseId): ?>
        <!-- Hidden input ensures the value submits correctly even if the browser ignores disabled options -->
        <input type="hidden" name="course_id" value="<?php echo (int)$lockedCourseId; ?>">
      <?php endif; ?>

      <?php if (isset($errors['course_id'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['course_id']); ?></div>
      <?php endif; ?>
    </div>

    <!-- campuses -->
    <div class="form-group">
      <label for="campus_id"><span class="req">*</span> Campus</label>
      <select id="campus_id" name="campus_id" required
              class="<?php echo isset($errors['campus_id']) ? 'input-error' : ''; ?>">
        <option value="">Select Campus…</option>
        <?php
          $stmt2 = $pdo->prepare("SELECT id, code, name FROM campuses ORDER BY name ASC");
          $stmt2->execute();
          $campuses = $stmt2->fetchAll(PDO::FETCH_ASSOC);
          foreach ($campuses as $cam):
        ?>
          <option value="<?php echo (int)$cam['id']; ?>"
            <?php if (!empty($_POST['campus_id']) && (int)$_POST['campus_id'] === (int)$cam['id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($cam['name']) . " (" . htmlspecialchars($cam['code']) . ")"; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['campus_id'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['campus_id']); ?></div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn-primary">Continue</button>
  </form>
</section>

<?php require_once __DIR__ . '/footer_user.php'; ?>
