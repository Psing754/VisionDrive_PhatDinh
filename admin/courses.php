<?php
$PAGE_TITLE = 'Manage Courses';
$BREADCRUMB = 'Admin / Manage Courses';
require 'header.php';

/* ==============================
   CONFIG — Upload
   ============================== */
$UPLOAD_DIR = __DIR__ . '/uploads/courses';
$UPLOAD_URL = '/admin/uploads/courses';

$ALLOWED_EXT = ['jpg','jpeg','png','webp'];
$MAX_SIZE = 2 * 1024 * 1024; // 2MB

if (!is_dir($UPLOAD_DIR)) {
  @mkdir($UPLOAD_DIR, 0775, true);
}

/* ==============================
   DELETE
   ============================== */
if (isset($_GET['delete_id'])) {
  $id = (int) $_GET['delete_id'];
  $old = qone("SELECT picture_url FROM courses WHERE id=?", [$id]);
  if ($old && !empty($old['picture_url'])) {
    $path = $old['picture_url'];
    if ($path[0] === '/') {
      $abs = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $path;
    } else {
      $abs = __DIR__ . '/' . ltrim($path, '/');
    }
    if (is_file($abs)) { @unlink($abs); }
  }
  qexec("DELETE FROM courses WHERE id=?", [$id]);
  header('Location: courses.php?msg=Deleted+course+' . $id);
  exit;
}

/* ==============================
   SAVE (create/update)
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
  $id            = (int) ($_POST['id'] ?? 0);
  $name          = trim($_POST['name'] ?? '');
  $category      = trim($_POST['category'] ?? '');
  $duration_val  = (int) ($_POST['duration_value'] ?? 0);
  $duration_unit = ($_POST['duration_unit'] ?? 'hours') === 'days' ? 'days' : 'hours';
  $status        = in_array($_POST['status'] ?? 'active', ['active','inactive'], true) ? $_POST['status'] : 'active';
  $desc          = trim($_POST['description'] ?? '');
  $price         = is_numeric($_POST['price'] ?? '') ? (float) $_POST['price'] : 0;
  $gst_included  = isset($_POST['gst_included']) ? 1 : 0;
  $duration_hours = $duration_val;

  $picture_url = null;
  $has_new_file = isset($_FILES['picture']) && is_uploaded_file($_FILES['picture']['tmp_name']);
  if ($has_new_file) {
    $f        = $_FILES['picture'];
    $ext      = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime_ok  = in_array($ext, $ALLOWED_EXT, true);
    $size_ok  = ($f['size'] <= $MAX_SIZE);
    if (!$mime_ok) { header('Location: courses.php?msg=Invalid+image+type'); exit; }
    if (!$size_ok) { header('Location: courses.php?msg=Image+too+large'); exit; }
    $safeBase = preg_replace('/[^a-z0-9-_]+/i', '_', pathinfo($f['name'], PATHINFO_FILENAME));
    $fileName = $safeBase . '_' . time() . '.' . $ext;
    $destAbs  = rtrim($UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($f['tmp_name'], $destAbs)) {
      header('Location: courses.php?msg=Upload+failed'); exit;
    }
    $picture_url = $UPLOAD_URL . '/' . $fileName;
  }

  if ($id > 0) {
    if ($picture_url) {
      $old = qone("SELECT picture_url FROM courses WHERE id=?", [$id]);
      if ($old && !empty($old['picture_url'])) {
        $path = $old['picture_url'];
        $abs = ($path[0] === '/') ? (rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $path) : (__DIR__ . '/' . ltrim($path, '/'));
        if (is_file($abs)) { @unlink($abs); }
      }
      qexec(
        "UPDATE courses SET name=?, category=?, description=?, duration_hours=?, duration_unit=?, price=?, gst_included=?, status=?, picture_url=? WHERE id=?",
        [$name, $category, $desc, $duration_hours, $duration_unit, $price, $gst_included, $status, $picture_url, $id]
      );
    } else {
      qexec(
        "UPDATE courses SET name=?, category=?, description=?, duration_hours=?, duration_unit=?, price=?, gst_included=?, status=? WHERE id=?",
        [$name, $category, $desc, $duration_hours, $duration_unit, $price, $gst_included, $status, $id]
      );
    }
    header('Location: courses.php?msg=Updated+course'); exit;
  } else {
    qexec(
      "INSERT INTO courses(name, category, description, duration_hours, duration_unit, price, gst_included, status, picture_url)
       VALUES(?,?,?,?,?,?,?,?,?)",
      [$name, $category, $desc, $duration_hours, $duration_unit, $price, $gst_included, $status, $picture_url]
    );
    header('Location: courses.php?msg=Created+course'); exit;
  }
}

/* ==============================
   LOAD edit
   ============================== */
$editCourse = null;
if (isset($_GET['edit_id'])) {
  $eid = (int) $_GET['edit_id'];
  $editCourse = qone("SELECT * FROM courses WHERE id=?", [$eid]);
}

$rows = qall("SELECT id,name,category,description,duration_hours,duration_unit,price,gst_included,status,picture_url FROM courses ORDER BY id DESC");
?>

<div class="toolbar d-flex flex-wrap gap-2 align-items-center">
  <input class="input form-control" style="max-width:260px" type="search" placeholder="Search courses…">
  <div id="bulk-inline" class="bulk-inline" hidden>
    <span class="bulk-count"><span id="bulk-inline-count">0</span> selected</span>
    <button id="bulk-inline-delete" type="button" class="btn" style="background:#ef4444;color:#fff;border:0">Delete</button>
  </div>

  <div class="spacer flex-grow-1"></div>
  <a class="btn primary btn btn-primary" href="courses.php?new=1">Add new</a>
</div>

<?php if (isset($_GET['new']) || $editCourse):
  $c = $editCourse ?? [
    'id'=>0,'name'=>'','category'=>'','duration_hours'=>8,'duration_unit'=>'hours',
    'status'=>'active','description'=>'','price'=>0,'gst_included'=>1,'picture_url'=>null
  ];
  $duration_value = (int)$c['duration_hours'];
?>
  <div class="vd-modal is-open" id="vd-modal-course" aria-modal="true" role="dialog">
    <div class="vd-modal__backdrop" data-vd-close></div>
    <div class="vd-modal__card" role="document">
      <div class="vd-modal__header">
        <h3 class="vd-modal__title"><?= $c['id'] ? 'Edit Course' : 'New Course' ?></h3>
        <a class="vd-modal__close" href="courses.php" aria-label="Close">×</a>
      </div>

      <div class="vd-modal__body">
        <form method="post" class="vd-form-grid" autocomplete="off" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">

          <label>Name
            <input name="name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>" required>
          </label>

          <label>Category
            <input name="category" class="form-control" placeholder="forklift / truck / safety…" value="<?= htmlspecialchars($c['category'] ?? '') ?>">
          </label>

          <label>Duration value
            <input type="number" name="duration_value" class="form-control" min="1" value="<?= (int)$duration_value ?>">
            <small class="text-muted">If Unit = days (e.g. 3), the calendar event will span multiple days.</small>
          </label>

          <label>Unit
            <select name="duration_unit" class="form-select">
              <option value="hours" <?= ($c['duration_unit']==='hours'?'selected':'') ?>>hours</option>
              <option value="days"  <?= ($c['duration_unit']==='days'?'selected':'')  ?>>days</option>
            </select>
          </label>

          <label>Price (numeric)
            <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= htmlspecialchars((string)$c['price']) ?>">
          </label>

          <label>GST included?
            <input type="checkbox" name="gst_included" value="1" <?= !empty($c['gst_included'])?'checked':'' ?>>
          </label>

          <label>Status
            <select name="status" class="form-select">
              <option value="active"   <?= $c['status']==='active'?'selected':'' ?>>active</option>
              <option value="inactive" <?= $c['status']==='inactive'?'selected':'' ?>>inactive</option>
            </select>
          </label>

          <label>Description
            <input name="description" class="form-control" value="<?= htmlspecialchars($c['description']) ?>">
          </label>

          <label>Picture (jpg/png/webp, ≤2MB)
            <input type="file" name="picture" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <?php if (!empty($c['picture_url'])): ?>
              <small>Current: <a href="<?= htmlspecialchars($c['picture_url']) ?>" target="_blank">view image</a></small>
            <?php endif; ?>
          </label>

          <div class="vd-form-actions">
            <button class="btn primary btn btn-primary" name="save_course" value="1">Save</button>
            <a class="btn btn-outline-secondary" href="courses.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <style>
    .vd-modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:16px;z-index:10000}
    .vd-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.35)}
    .vd-modal__card{position:relative;background:#fff;max-width:720px;width:100%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:16px}
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
      const m = document.getElementById('vd-modal-course');
      function closeOnEsc(e){ if(e.key==='Escape'){ window.location.href='courses.php'; } }
      if(m){ document.addEventListener('keydown', closeOnEsc);
        m.querySelectorAll('[data-vd-close]').forEach(x=>x.addEventListener('click', ()=>{ window.location.href='courses.php'; }));
      }
    })();
  </script>
<?php endif; ?>

<div class="table-wrap table-responsive mt-3">
  <table class="table table-hover align-middle table-sm" id="tbl-courses">
    <thead>
      <tr>
        <th></th>
        <th>Name</th>
        <th>Category</th>
        <th>Duration</th>
        <th>Price</th>
        <th>Status</th>
        <th>Description</th>
        <th>Image</th>
        <th class="text-nowrap">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $isIncl = !empty($r['gst_included']);
        $priceStr = number_format((float)$r['price'], 2);
        $priceDisp = '$' . $priceStr . ($isIncl ? ' (GST)' : '');
        $durDisp = (int)$r['duration_hours'] . ' ' . ($r['duration_unit']==='days' ? 'day(s)' : 'hour(s)');
      ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>"></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= htmlspecialchars($r['category'] ?? '') ?></td>
          <td><?= htmlspecialchars($durDisp) ?></td>
          <td><?= htmlspecialchars($priceDisp) ?></td>
          <td>
            <span class="badge <?= $r['status']==='active'?'success':'gray' ?> badge rounded-pill <?= $r['status']==='active'?'bg-success':'bg-secondary' ?>">
              <?= htmlspecialchars($r['status']) ?>
            </span>
          </td>
          <td><?= htmlspecialchars(mb_strimwidth($r['description'] ?? '', 0, 50, '…')) ?></td>
          <td>
            <?php if (!empty($r['picture_url'])): ?>
              <img src="<?= htmlspecialchars($r['picture_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;">
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td class="row-actions text-nowrap">
            <a href="courses.php?edit_id=<?= $r['id'] ?>" class="link"
               style="display:inline-flex;align-items:center;padding:.28rem .6rem;border-radius:.5rem;
                      font-weight:700;line-height:1.1;border:1px solid transparent;text-decoration:none;
                      color:#fff;background:var(--brand);box-shadow:inset 0 0 0 999px rgba(0,0,0,.12);">
              Edit
            </a>
            <a href="courses.php?delete_id=<?= $r['id'] ?>" class="link danger"
               onclick="return confirm('Delete this Course?')"
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
    const table = document.getElementById('tbl-courses');
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
      if(!confirm('Delete selected courses?')) return;
      const ids = Array.from(table.querySelectorAll('tbody input[type="checkbox"]:checked')).map(cb=>cb.value);
      if(ids.length===0) return;
      window.location.href = 'courses.php?delete_id='+encodeURIComponent(ids[0]);
    });
  })();
</script>

<?php require 'footer.php'; ?>
