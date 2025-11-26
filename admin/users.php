<?php
$PAGE_TITLE = 'Manage Users';
$BREADCRUMB = 'Admin / Manage Users';
require 'header.php';

/* DELETE */
if (isset($_GET['delete_id'])) {
  $id = (int) $_GET['delete_id'];
  qexec("DELETE FROM users WHERE id=?", [$id]);
  header('Location: users.php?msg=Deleted+user+' . $id);
  exit;
}

/* SAVE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
  $id        = isset($_POST['id']) ? (int) $_POST['id'] : 0;
  $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
  $email     = isset($_POST['email']) ? trim($_POST['email']) : '';
  $role_in   = isset($_POST['role']) ? $_POST['role'] : 'student';
  $allowed_roles = array('admin','staff','student');
  $role      = in_array($role_in, $allowed_roles, true) ? $role_in : 'student';

  if ($id > 0) {
    qexec("UPDATE users SET full_name=?, email=?, role=? WHERE id=?", array($full_name, $email, $role, $id));
    header('Location: users.php?msg=Updated+user'); exit;
  } else {
    $pwd  = isset($_POST['password']) && $_POST['password'] !== '' ? $_POST['password'] : '123456';
    $hash = password_hash($pwd, PASSWORD_BCRYPT);
    qexec("INSERT INTO users(full_name,email,role,password_hash) VALUES(?,?,?,?)", array($full_name,$email,$role,$hash));
    header('Location: users.php?msg=Created+user'); exit;
  }
}

/* LOAD edit */
$editUser = null;
if (isset($_GET['edit_id'])) {
  $eid = (int) $_GET['edit_id'];
  $editUser = qone("SELECT * FROM users WHERE id=?", array($eid));
}

$rows = qall("SELECT id, full_name, email, role FROM users ORDER BY id DESC");
?>

<div class="toolbar d-flex flex-wrap gap-2 align-items-center">
  <input class="input form-control" style="max-width:260px" type="search" placeholder="Search users…">
  <div id="bulk-inline" class="bulk-inline" hidden>
    <span class="bulk-count"><span id="bulk-inline-count">0</span> selected</span>
    <button id="bulk-inline-delete" type="button" class="btn" style="background:#ef4444;color:#fff;border:0">Delete</button>
  </div>

  <div class="spacer flex-grow-1"></div>
  <a class="btn primary btn btn-primary" href="users.php?new=1">Add New</a>
</div>

<?php
$defaultUser = array('id'=>0,'full_name'=>'','email'=>'','role'=>'student');
$u = $editUser ? $editUser : $defaultUser;

if (isset($_GET['new']) || $editUser):
?>
  <div class="vd-modal is-open" id="vd-modal-user" aria-modal="true" role="dialog">
    <div class="vd-modal__backdrop" data-vd-close></div>
    <div class="vd-modal__card" role="document">
      <div class="vd-modal__header">
        <h3 class="vd-modal__title"><?php echo $u['id'] ? 'Edit User' : 'New User'; ?></h3>
        <a class="vd-modal__close" href="users.php" aria-label="Close">×</a>
      </div>

      <div class="vd-modal__body">
         <form method="post" class="vd-form-grid" autocomplete="off">
          <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
          <label>Full Name
            <input name="full_name" class="form-control" value="<?php echo htmlspecialchars($u['full_name']); ?>" required>
          </label>
          <label>Email
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($u['email']); ?>" required>
          </label>
          <label>Role
            <select name="role" class="form-select">
              <?php foreach (array('student','staff','admin') as $rr): ?>
                <option value="<?php echo $rr; ?>" <?php echo ($u['role']===$rr?'selected':''); ?>><?php echo $rr; ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php if (!$editUser): ?>
            <label>Password
              <input name="password" class="form-control" placeholder="123456">
            </label>
          <?php endif; ?>
          <div class="vd-form-actions">
            <button class="btn primary btn btn-primary" name="save_user" value="1">Save</button>
            <a class="btn btn-outline-secondary" href="users.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <style>
    .vd-modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:16px;z-index:10000}
    .vd-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.35)}
    .vd-modal__card{position:relative;background:#fff;max-width:560px;width:100%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:16px}
    .vd-modal__header{display:flex;align-items:center;gap:8px}
    .vd-modal__title{margin:0;font-size:18px}
    .vd-modal__close{margin-left:auto;font-size:24px;text-decoration:none;line-height:1}
    .vd-modal__body{margin-top:12px}
    .vd-form-grid{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:12px}
    @media (max-width:700px){.vd-form-grid{grid-template-columns:1fr}}
    .vd-form-actions{grid-column:1/-1;display:flex;gap:8px}
    .is-open{visibility:visible;opacity:1}
  </style>
  <script>
    (function(){
      var m = document.getElementById('vd-modal-user');
      function closeOnEsc(e){ if(e.key==='Escape'){ window.location.href='users.php'; } }
      if(m){
        document.addEventListener('keydown', closeOnEsc);
        var backs = m.querySelectorAll('[data-vd-close]');
        for (var i=0;i<backs.length;i++){
          backs[i].addEventListener('click', function(){ window.location.href='users.php'; });
        }
        var first = m.querySelector('input, select, textarea');
        if (first) setTimeout(function(){ first.focus(); }, 0);
      }
    })();
  </script>
<?php endif; ?>

<div class="table-wrap table-responsive mt-3">
  <table class="table table-hover align-middle table-sm" id="tbl-users">
    <thead>
      <tr>
        <!-- ĐÃ BỎ checkbox “select all”  -->
        <th><!-- Select --></th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Role</th>
        <th class="text-nowrap">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $cls = $r['role']==='admin' ? 'dark' : ($r['role']==='staff' ? 'blue' : 'gray');
        $bs  = $r['role']==='admin' ? 'bg-dark' : ($r['role']==='staff' ? 'bg-info' : 'bg-secondary');
      ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?php echo $r['id']; ?>"></td>
          <td><?php echo htmlspecialchars($r['full_name']); ?></td>
          <td><?php echo htmlspecialchars($r['email']); ?></td>
          <td><span class="badge <?php echo $cls; ?> badge rounded-pill <?php echo $bs; ?>"><?php echo htmlspecialchars($r['role']); ?></span></td>
          <td class="row-actions text-nowrap">
            <a href="users.php?edit_id=<?= $r['id'] ?>" class="link"
               style="display:inline-flex;align-items:center;padding:.28rem .6rem;border-radius:.5rem;
                      font-weight:700;line-height:1.1;border:1px solid transparent;text-decoration:none;
                      color:#fff;background:var(--brand);box-shadow:inset 0 0 0 999px rgba(0,0,0,.12);">
              Edit
            </a>

            <a href="users.php?delete_id=<?= $r['id'] ?>" class="link danger"
               onclick="return confirm('Delete this user?')"
               style="display:inline-flex;align-items:center;padding:.28rem .6rem;border-radius:.5rem;
                      font-weight:700;line-height:1.1;border:1px solid transparent;text-decoration:none;
                      color:#fff;background:var(--red);box-shadow:inset 0 0 0 999px rgba(0,0,0,.14);margin-left:.4rem;">
              Delete
            </a>
          </td>
        </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<script>
  (function(){
    const table = document.getElementById('tbl-users');
    const bulk  = document.getElementById('bulk-inline');
    const count = document.getElementById('bulk-inline-count');
    function updateBulk(){
      const checked = table ? table.querySelectorAll('tbody input[type="checkbox"]:checked').length : 0;
      count.textContent = checked;
      bulk.hidden = checked === 0;
    }
    table?.addEventListener('change', updateBulk);
    updateBulk();

    document.getElementById('bulk-inline-delete')?.addEventListener('click', ()=>{
      if(!confirm('Delete selected users?')) return;
      const ids = Array.from(table.querySelectorAll('tbody input[type="checkbox"]:checked')).map(cb=>cb.value);
      if(ids.length===0) return;
      window.location.href = 'users.php?delete_id='+encodeURIComponent(ids[0]);
    });
  })();
</script>

<?php require 'footer.php'; ?>
