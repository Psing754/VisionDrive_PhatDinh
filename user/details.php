<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* Must have offering + date chosen earlier */
if (empty($_SESSION['offering_id']) || empty($_SESSION['training_date'])) {
    header("Location: /user/calendar.php");
    exit;
}

/* ===== SHORT PATH: already logged-in student => create booking and redirect ===== */
if (!empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'student')) {
    try {
        $offering_id   = (int)$_SESSION['offering_id'];
        $training_date = $_SESSION['training_date'];
        $user_id       = (int)$_SESSION['user_id'];

        // sanity check offering
        $off = qone("SELECT id FROM offerings WHERE id=? LIMIT 1", [$offering_id]);
        if (!$off) { throw new Exception("Offering not found"); }

        // avoid duplicate booking (same user + offering + date)
        $exists = qone(
            "SELECT id FROM enrollments WHERE user_id=? AND offering_id=? AND training_date=? LIMIT 1",
            [$user_id, $offering_id, $training_date]
        );
        if (!$exists) {
            qexec(
                "INSERT INTO enrollments (user_id,offering_id,training_date,status,created_at,updated_at)
                 VALUES (?,?,?,?,NOW(),NOW())",
                [$user_id, $offering_id, $training_date, 'reserved']
            );
        }

        $_SESSION['just_booked_name'] = $_SESSION['user_name'] ?? 'there';
        unset($_SESSION['course_id'], $_SESSION['campus_id'], $_SESSION['offering_id'], $_SESSION['training_date']);
        header("Location: /user/success.php");
        exit;

    } catch (Throwable $e) {
        // fall back to form if error
        $errors = ['fatal' => "DB ERROR: " . $e->getMessage()];
    }
}

/* ===== LONG PATH: guest user => show details form, upsert user, then create booking ===== */
$errors = $errors ?? [];

/* helpers (old inputs) */
function old($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

/* seed fields */
$first_name = $_POST['first_name'] ?? '';
$last_name  = $_POST['last_name'] ?? '';
$email      = $_POST['email'] ?? '';
$phone      = $_POST['phone'] ?? '';
$region     = $_POST['region'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===== VALIDATION =====
    if (!preg_match('/^[a-zA-Z\s]{1,120}$/', $first_name)) {
        $errors['first_name'] = "First Name is required and must only contain letters/spaces.";
    }
    // Last name optional, but if present, letters/spaces only
    if ($last_name !== '' && !preg_match('/^[a-zA-Z\s]{1,120}$/', $last_name)) {
        $errors['last_name'] = "Last Name can only contain letters/spaces.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Email format is invalid.";
    }
    if (!preg_match('/^[0-9]{7,10}$/', $phone)) {
        $errors['phone'] = "Phone must be 7-10 digits.";
    }
    if (trim($region) === '') {
        $errors['region'] = "Region is required.";
    }

    // ===== File upload (identity doc - REQUIRED) =====
    $uploadedFilePath = null;
    $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
    $maxSize     = 2 * 1024 * 1024; // 2MB

    if (empty($_FILES['identity_doc']['name'])) {
        $errors['identity_doc'] = "Identity document is required.";
    } else {
        if ($_FILES['identity_doc']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['identity_doc']['tmp_name'];
            $mime    = mime_content_type($tmpName);
            $size    = $_FILES['identity_doc']['size'];

            if (!in_array($mime, $allowedMime, true)) {
                $errors['identity_doc'] = "Only JPG, PNG or PDF allowed.";
            } elseif ($size > $maxSize) {
                $errors['identity_doc'] = "File too large. Max 2MB.";
            } else {
                $ext = pathinfo($_FILES['identity_doc']['name'], PATHINFO_EXTENSION);
                $safeName = 'id_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                // Save under /user/userassets/uploads (dir is relative to this file)
                $uploadDir = __DIR__ . '/userassets/uploads/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }

                $dest = $uploadDir . $safeName;
                if (!move_uploaded_file($tmpName, $dest)) {
                    $errors['identity_doc'] = "Failed to save the file.";
                } else {
                    // Public URL to stored file
                    $uploadedFilePath = '/user/userassets/uploads/' . $safeName;
                }
            }
        } else {
            $errors['identity_doc'] = "Error uploading file.";
        }
    }

    // ===== If no errors => upsert user + create booking =====
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $offering_id   = (int)$_SESSION['offering_id'];
            $training_date = $_SESSION['training_date'];
            $off = qone("SELECT id FROM offerings WHERE id=? LIMIT 1", [$offering_id]);
            if (!$off) { throw new Exception("Offering not found"); }

            $full_name    = trim(preg_replace('/\s+/', ' ', $first_name . ' ' . $last_name));
            $default_hash = password_hash("123466", PASSWORD_DEFAULT);

            // upsert user by email
            qexec(
                "INSERT INTO users (full_name, email, phone, region, role, password_hash, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'student', ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                   full_name  = VALUES(full_name),
                   phone      = VALUES(phone),
                   region     = VALUES(region),
                   role       = IF(role IN ('admin','staff'), role, 'student'),
                   updated_at = NOW()",
                [$full_name, $email, $phone, $region, $default_hash]
            );

            $urow = qone("SELECT id FROM users WHERE email=? LIMIT 1", [$email]);
            if (!$urow) { throw new Exception("User upsert failed"); }
            $user_id = (int)$urow['id'];

            if (!empty($uploadedFilePath)) {
                qexec(
                    "INSERT INTO identity_docs (user_id, doc_type, file_url, created_at, updated_at)
                     VALUES (?, 'id_upload', ?, NOW(), NOW())",
                    [$user_id, $uploadedFilePath]
                );
            }

            // avoid duplicate enrollment
            $exists = qone(
                "SELECT id FROM enrollments WHERE user_id=? AND offering_id=? AND training_date=? LIMIT 1",
                [$user_id, $offering_id, $training_date]
            );
            if (!$exists) {
                qexec(
                    "INSERT INTO enrollments (user_id, offering_id, training_date, status, created_at, updated_at)
                     VALUES (?, ?, ?, 'reserved', NOW(), NOW())",
                    [$user_id, $offering_id, $training_date]
                );
            }

            // mark session as logged-in student
            $_SESSION['user_id']   = $user_id;
            $_SESSION['user_name'] = $full_name;
            $_SESSION['role']      = 'student';
            $_SESSION['email']     = $email;

            $pdo->commit();

            // very simple emails
            $first_for_greeting = $first_name ?: explode(' ', $full_name)[0];
            @mail(
                $email,
                "Your VisionDrive booking",
                "Hi $first_for_greeting,\n\nThank you for registering. We have received your booking.\nWe will contact you with payment instructions.\n\nYour login account has been created:\nEmail: $email\nPassword: 123466\nPlease change your password later.\n\nVisionDrive Training"
            );
            @mail(
                "admin@example.com",
                "New enrolment received",
                "New enrolment:\nName: $full_name\nEmail: $email\nPhone: $phone\nRegion: $region\nTraining date: $training_date\nOffering ID: $offering_id\n"
            );

            $_SESSION['just_booked_name'] = $first_for_greeting;
            unset($_SESSION['course_id'], $_SESSION['campus_id'], $_SESSION['offering_id'], $_SESSION['training_date']);
            header("Location: /user/success.php");
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors['fatal'] = "DB ERROR: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header_user.php';
?>
<section class="step-wrapper">
  <h1 class="step-title">Please fill your details</h1>
  <p class="step-hint">All fields marked <span class="req">*</span> are required</p>

  <?php if (!empty($errors['fatal'])): ?>
    <div class="form-error" role="alert"><?php echo htmlspecialchars($errors['fatal']); ?></div>
  <?php endif; ?>

  <!-- Details form for guest users -->
  <form class="vd-form" method="post" action="/user/details.php" enctype="multipart/form-data" novalidate aria-labelledby="detailsHeading">
    <!-- First name -->
    <div class="form-group">
      <label for="first_name">
        <span class="req">*</span> First Name
      </label>
      <input
        type="text"
        id="first_name"
        name="first_name"
        value="<?php echo old('first_name', $first_name); ?>"
        class="<?php echo isset($errors['first_name']) ? 'input-error' : ''; ?>"
        required
        aria-describedby="firstNameHelp"
      >
      <?php if (isset($errors['first_name'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['first_name']); ?></div>
      <?php else: ?>
        <div id="firstNameHelp" class="hint">Letters and spaces only.</div>
      <?php endif; ?>
    </div>

    <!-- Last name -->
    <div class="form-group">
      <label for="last_name">
        Last Name
      </label>
      <input
        type="text"
        id="last_name"
        name="last_name"
        value="<?php echo old('last_name', $last_name); ?>"
        class="<?php echo isset($errors['last_name']) ? 'input-error' : ''; ?>"
        aria-describedby="lastNameHelp"
      >
      <?php if (isset($errors['last_name'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['last_name']); ?></div>
      <?php else: ?>
        <div id="lastNameHelp" class="hint">Optional; letters and spaces only.</div>
      <?php endif; ?>
    </div>

    <!-- Email -->
    <div class="form-group">
      <label for="email">
        <span class="req">*</span> Email
      </label>
      <input
        type="email"
        id="email"
        name="email"
        value="<?php echo old('email', $email); ?>"
        class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
        required
      >
      <?php if (isset($errors['email'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['email']); ?></div>
      <?php endif; ?>
    </div>

    <!-- Phone -->
    <div class="form-group">
      <label for="phone">
        <span class="req">*</span> Phone
      </label>
      <input
        type="text"
        id="phone"
        name="phone"
        value="<?php echo old('phone', $phone); ?>"
        class="<?php echo isset($errors['phone']) ? 'input-error' : ''; ?>"
        required
        aria-describedby="phoneHelp"
        inputmode="numeric"
        pattern="[0-9]{7,10}"
      >
      <?php if (isset($errors['phone'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['phone']); ?></div>
      <?php else: ?>
        <div id="phoneHelp" class="hint">Digits only, 7–10 characters.</div>
      <?php endif; ?>
    </div>

    <!-- Region -->
    <div class="form-group">
      <label for="region">
        <span class="req">*</span> Region
      </label>
      <select
        id="region"
        name="region"
        class="<?php echo isset($errors['region']) ? 'input-error' : ''; ?>"
        required
      >
        <option value="">Choose a region…</option>
        <?php
          $regions = [
            'Auckland','Wellington','Canterbury','Waikato','Otago','Bay of Plenty',
            'Hawke\'s Bay','Northland','Manawatū-Whanganui','Taranaki','Nelson',
            'Tasman','Marlborough','West Coast','Southland','Gisborne'
          ];
          foreach ($regions as $rOpt):
            $sel = ($region === $rOpt) ? 'selected' : '';
        ?>
          <option value="<?php echo htmlspecialchars($rOpt); ?>" <?php echo $sel; ?>>
            <?php echo htmlspecialchars($rOpt); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['region'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['region']); ?></div>
      <?php endif; ?>
    </div>

    <!-- Identity document -->
    <div class="form-group">
      <label for="identity_doc">
        <span class="req">*</span> Identity document (JPG/PNG/PDF, ≤ 2MB)
      </label>
      <input
        type="file"
        id="identity_doc"
        name="identity_doc"
        accept=".jpg,.jpeg,.png,.pdf"
        required
        class="<?php echo isset($errors['identity_doc']) ? 'input-error' : ''; ?>"
        aria-describedby="idHelp"
      >
      <?php if (isset($errors['identity_doc'])): ?>
        <div class="field-error" role="alert"><?php echo htmlspecialchars($errors['identity_doc']); ?></div>
      <?php else: ?>
        <div id="idHelp" class="hint">Please upload a clear copy of your ID.</div>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="calendar-actions">
      <a class="btn-secondary" href="/user/calendar.php">Back</a>
      <button type="submit" class="btn-primary">Submit</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/footer_user.php'; ?>
