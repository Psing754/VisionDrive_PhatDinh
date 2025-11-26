<?php
$PAGE_TITLE  = 'Manage Campuses';
$BREADCRUMB  = 'Admin / Manage Campuses';
require 'header.php';

/* DELETE */
if (isset($_GET['delete_id'])) {
  $id = (int) $_GET['delete_id'];
  qexec("DELETE FROM campuses WHERE id=?", [$id]);
  header('Location: campuses.php?msg=Deleted+campus+' . $id);
  exit;
}

/* SAVE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campus'])) {
  $id      = (int) ($_POST['id'] ?? 0);
  $code    = trim($_POST['code'] ?? '');
  $name    = trim($_POST['name'] ?? '');
  $address = trim($_POST['address'] ?? '');

  if ($id > 0) {
    qexec("UPDATE campuses SET code=?, name=?, address=? WHERE id=?", [$code, $name, $address, $id]);
    header('Location: campuses.php?msg=Updated+campus'); exit;
  } else {
    qexec("INSERT INTO campuses(code,name,address) VALUES(?,?,?)", [$code,$name,$address]);
    header('Location: campuses.php?msg=Created+campus'); exit;
  }
}

/* LOAD edit */
$editCampus = null;
if (isset($_GET['edit_id'])) {
  $eid = (int) $_GET['edit_id'];
  $editCampus = qone("SELECT * FROM campuses WHERE id=?", [$eid]);
}

/* LIST */
$rows = qall("SELECT id, code, name, address FROM campuses ORDER BY id DESC");
?>

<div class="toolbar d-flex flex-wrap gap-2 align-items-center">
  <input class="input form-control" style="max-width:260px" type="search" placeholder="Search campuses…" oninput="
    const q=this.value.toLowerCase();
    document.querySelectorAll('#tbl-campuses tbody tr').forEach(tr=>{
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  ">
  <div id="bulk-inline" class="bulk-inline" hidden>
    <span class="bulk-count"><span id="bulk-inline-count">0</span> selected</span>
    <button id="bulk-inline-delete" type="button" class="btn" style="background:#ef4444;color:#fff;border:0">Delete</button>
  </div>

  <div class="spacer flex-grow-1"></div>
  <a class="btn primary btn btn-primary" href="campuses.php?new=1">Add new</a>
</div>

<?php if (isset($_GET['new']) || $editCampus):
  $c = $editCampus ?? ['id'=>0,'code'=>'','name'=>'','address'=>'']; ?>

  <div class="vd-modal is-open" id="vd-modal-campus" aria-modal="true" role="dialog">
    <div class="vd-modal__backdrop" data-vd-close></div>
    <div class="vd-modal__card" role="document">
      <div class="vd-modal__header">
        <h3 class="vd-modal__title"><?= $c['id'] ? 'Edit Campus' : 'New Campus' ?></h3>
        <a class="vd-modal__close" href="campuses.php" aria-label="Close">×</a>
      </div>

      <div class="vd-modal__body">
        <form method="post" class="vd-form-grid" autocomplete="off">
          <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">

          <label>Code
            <input name="code" class="form-control" value="<?= htmlspecialchars($c['code']) ?>" required>
          </label>

          <label>Name
            <input name="name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>" required>
          </label>

          <label style="grid-column:1/-1">Address
            <input name="address" class="form-control" value="<?= htmlspecialchars($c['address']) ?>">
          </label>

          <div class="vd-form-actions">
            <button class="btn primary btn btn-primary" name="save_campus" value="1">Save</button>
            <a class="btn btn-outline-secondary" href="campuses.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <style>
    .vd-modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:16px;z-index:10000}
    .vd-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.35)}
    .vd-modal__card{position:relative;background:#fff;max-width:600px;width:100%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:16px}
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
      const m = document.getElementById('vd-modal-campus');
      function closeOnEsc(e){ if(e.key==='Escape'){ window.location.href='campuses.php'; } }
      if(m){
        document.addEventListener('keydown', closeOnEsc);
        m.querySelectorAll('[data-vd-close]').forEach(x=>x.addEventListener('click', ()=>{ window.location.href='campuses.php'; }));
      }
    })();
  </script>
<?php endif; ?>

<div class="table-wrap table-responsive mt-3">
  <table class="table table-hover align-middle table-sm" id="tbl-campuses">
    <thead>
      <tr>
        <th></th>
        <th>Code</th>
        <th>Name</th>
        <th>Address</th>
        <th class="text-nowrap">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>"></td>
          <td><?= htmlspecialchars($r['code']) ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= htmlspecialchars($r['address']) ?></td>
          <td class="row-actions text-nowrap">
            <a href="campuses.php?edit_id=<?= $r['id'] ?>" class="link"
               style="display:inline-flex;align-items:center;padding:.28rem .6rem;border-radius:.5rem;
                      font-weight:700;line-height:1.1;border:1px solid transparent;text-decoration:none;
                      color:#fff;background:var(--brand);box-shadow:inset 0 0 0 999px rgba(0,0,0,.12);">
              Edit
            </a>

            <a href="campuses.php?delete_id=<?= $r['id'] ?>" class="link danger"
               onclick="return confirm('Delete this Campus?')"
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
  // Toggle bulk bar when selecting rows
  (function(){
    const table = document.getElementById('tbl-campuses');
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
      if(!confirm('Delete selected campuses?')) return;
      const ids = Array.from(table.querySelectorAll('tbody input[type="checkbox"]:checked')).map(cb=>cb.value);
      if(ids.length===0) return;
      window.location.href = 'campuses.php?delete_id='+encodeURIComponent(ids[0]);
    });
  })();
</script>

<?php require 'footer.php'; ?>
