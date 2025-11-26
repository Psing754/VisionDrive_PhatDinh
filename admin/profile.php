<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$PAGE_TITLE  = 'My Profile';
$BREADCRUMB  = 'Admin / My Profile';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

$user = qone("SELECT id, full_name, email, role FROM users WHERE id=?", [$user_id]);
if (!$user) { die('User not found'); }

$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
  $full_name = trim($_POST['full_name'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $cur_pass  = $_POST['current_password'] ?? '';
  $new_pass  = $_POST['new_password'] ?? '';
  $new_conf  = $_POST['confirm_password'] ?? '';

  if ($full_name === '' || $email === '') {
    $err = 'Full name and email are required.';
  } else {
    try {
      // Change password only if provided
      if ($new_pass !== '' || $new_conf !== '') {
        if ($new_pass !== $new_conf) {
          throw new Exception('New password and confirmation do not match.');
        }
        if (strlen($new_pass) < 6) {
          throw new Exception('Password must be at least 6 characters.');
        }
        $pwdRow = qone("SELECT password_hash FROM users WHERE id=?", [$user_id]);
        if (!$pwdRow || !password_verify($cur_pass, $pwdRow['password_hash'])) {
          throw new Exception('Current password is incorrect.');
        }
        $newHash = password_hash($new_pass, PASSWORD_DEFAULT);
        qexec("UPDATE users SET password_hash=? WHERE id=?", [$newHash, $user_id]);
      }

      qexec("UPDATE users SET full_name=?, email=? WHERE id=?", [$full_name, $email, $user_id]);
      $_SESSION['user_name'] = $full_name;

      $msg  = 'Profile updated.';
      $user = qone("SELECT id, full_name, email, role FROM users WHERE id=?", [$user_id]);
    } catch (Throwable $e) {
      $err = $e->getMessage();
    }
  }
}

require 'header.php';
?>
<style>
  /* Minimal, kiểu Google: trắng, thoáng, tập trung nội dung */
  .g-wrap { max-width: 760px; margin: 0 auto; }
  .g-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:16px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
  }
  .g-head {
    display:flex; align-items:center; gap:16px; padding:20px 24px; border-bottom:1px solid #f1f5f9;
  }
  .g-ava {
    width:64px; height:64px; border-radius:50%;
    background:#eef2ff; color:#1e293b; display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:26px;
    border:1px solid #e5e7eb;
  }
  .g-name { font-size:20px; font-weight:700; color:#0f172a; }
  .g-email{ color:#475569; font-size:14px; }
  .g-body { padding:20px 24px; }
  .g-row  { display:grid; grid-template-columns: 160px 1fr; gap:16px; align-items:center; padding:10px 0; }
  .g-label{ color:#475569; font-weight:600; }
  .g-actions { display:flex; justify-content:flex-end; gap:8px; padding:16px 24px; border-top:1px solid #f1f5f9; }
  .btn { background:#f8fafc; border:1px solid #e5e7eb; color:#0f172a; padding:8px 14px; border-radius:10px; cursor:pointer; }
  .btn:hover{ filter:brightness(1.03); }
  .btn-primary { background: var(--brand, #2563eb); color:#fff; border: none; }
  .g-alert { border-radius:10px; padding:10px 12px; margin-bottom:12px; }
  .g-alert.success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
  .g-alert.error   { background:#fef2f2; color:#7f1d1d; border:1px solid #fecaca; }
  details.g-sec { border-top:1px solid #f1f5f9; padding-top:10px; margin-top:6px; }
  details.g-sec > summary {
    list-style: none; cursor:pointer; font-weight:700; color:#0f172a; padding:6px 0;
  }
  details.g-sec > summary::-webkit-details-marker { display:none; }
  details.g-sec[open] > summary { color:#111827; }
  @media (max-width: 640px){
    .g-row{ grid-template-columns: 1fr; }
  }
</style>

<div class="g-wrap py-3">
  <?php if ($msg): ?><div class="g-alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="g-alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post" class="g-card">
    <!-- Header -->
    <div class="g-head">
      <div class="g-ava" aria-hidden="true">
        <?php
          $initial = strtoupper(mb_substr($user['full_name'] ?? 'A', 0, 1, 'UTF-8'));
          echo htmlspecialchars($initial);
        ?>
      </div>
      <div>
        <div class="g-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="g-email"><?= htmlspecialchars($user['email']) ?></div>
      </div>
      <div style="margin-left:auto">
        <a href="dashboard.php" class="btn">Back</a>
      </div>
    </div>

    <!-- Body -->
    <div class="g-body">
      <div class="g-row">
        <div class="g-label">Full name</div>
        <div><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required></div>
      </div>
      <div class="g-row">
        <div class="g-label">Email</div>
        <div><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required></div>
      </div>

      <details class="g-sec">
        <summary>Change password</summary>
        <div class="g-row" style="margin-top:8px;">
          <div class="g-label">Current password</div>
          <div><input type="password" name="current_password" class="form-control" placeholder="Enter current password"></div>
        </div>
        <div class="g-row">
          <div class="g-label">New password</div>
          <div><input type="password" name="new_password" id="new_password" class="form-control" placeholder="Min 6 characters"></div>
        </div>
        <div class="g-row">
          <div class="g-label">Confirm password</div>
          <div><input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter new password"></div>
        </div>
        <div class="g-row">
          <div></div>
          <div><small id="pwdHint" class="text-muted"></small></div>
        </div>
      </details>
    </div>

    <!-- Actions -->
    <div class="g-actions">
      <a href="dashboard.php" class="btn">Cancel</a>
      <button class="btn btn-primary" name="save_profile" value="1">Save</button>
    </div>
  </form>
</div>

<script>
(function(){
  const a = document.getElementById('new_password');
  const b = document.getElementById('confirm_password');
  const hint = document.getElementById('pwdHint');
  function say(t, ok){
    if(!hint) return; hint.textContent = t || '';
    hint.style.color = ok ? '#065f46' : '#7f1d1d';
  }
  function check(){
    if(!a || !b) return;
    const x=a.value||'', y=b.value||'';
    if(x==='' && y===''){ say(''); return; }
    if(x.length<6){ say('Password should be at least 6 characters.', false); return; }
    if(x!==y){ say('Passwords do not match.', false); return; }
    say('Looks good ✔', true);
  }
  if(a) a.addEventListener('input', check);
  if(b) b.addEventListener('input', check);
})();
</script>

<?php require 'footer.php'; ?>
