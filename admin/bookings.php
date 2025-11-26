<?php 
$PAGE_TITLE = 'Manage Bookings';
$BREADCRUMB = 'Admin / Manage Bookings';
require 'header.php';

/* ==============================
   QUERY PARAMS (search/filter/sort/paging)
   ============================== */
$q        = trim($_GET['q'] ?? '');
$statusF  = $_GET['status'] ?? '';
$catF     = trim($_GET['cat'] ?? '');           // category filter
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

/* SORT: only 3 choices (date, student, course) */
$sortKey = $_GET['sort'] ?? 'date';
$dir     = strtolower($_GET['dir'] ?? 'desc');
$dir     = $dir === 'asc' ? 'asc' : 'desc';

$sortMap = [
  'date'    => "COALESCE(e.training_date, o.start_date)", // by training date
  'student' => "u.full_name",
  'course'  => "c.name",
];
$sortCol = $sortMap[$sortKey] ?? $sortMap['date'];

/* ==============================
   DELETE
   ============================== */
if (isset($_GET['delete_id'])) {
  $id = (int) $_GET['delete_id'];
  qexec("DELETE FROM enrollments WHERE id=?", [$id]);
  header('Location: bookings.php?msg=Deleted+booking+' . $id);
  exit;
}

/* ==============================
   SAVE (create/update) — use user_id instead of student_id
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_booking'])) {
  $id            = (int) ($_POST['id'] ?? 0);
  $user_id       = (int) ($_POST['user_id'] ?? 0);
  $offering_id   = (int) ($_POST['offering_id'] ?? 0);
  $training_date = $_POST['training_date'] ?? date('Y-m-d');
  $status        = $_POST['status'] ?? 'reserved';
  $allow = ['reserved','confirmed','attended','no_show','cancelled'];
  if (!in_array($status, $allow, true)) $status = 'reserved';

  if ($id > 0) {
    qexec("UPDATE enrollments SET user_id=?,offering_id=?,training_date=?,status=? WHERE id=?",
      [$user_id,$offering_id,$training_date,$status,$id]);
    header('Location: bookings.php?msg=Updated+booking'); exit;
  } else {
    qexec("INSERT INTO enrollments(user_id,offering_id,training_date,status) VALUES(?,?,?,?)",
      [$user_id,$offering_id,$training_date,$status]);
    header('Location: bookings.php?msg=Created+booking'); exit;
  }
}

/* ==============================
   LOAD record for edit
   ============================== */
$editBk = null;
if (isset($_GET['edit_id'])) {
  $eid = (int) $_GET['edit_id'];
  $editBk = qone("SELECT * FROM enrollments WHERE id=?", [$eid]);
}

/* ==============================
   Options (students & offerings)
   ============================== */
$students = qall("SELECT id, full_name AS n FROM users WHERE role='student' ORDER BY full_name");

$offers = qall("
  SELECT o.id, CONCAT(c.name,' - ',ca.code,' - ',o.start_date) AS n
  FROM offerings o
  JOIN courses  c ON c.id=o.course_id
  JOIN campuses ca ON ca.id=o.campus_id
  ORDER BY o.start_date DESC
");

/* Category dropdown options (distinct from courses) */
$categories = qall("
  SELECT DISTINCT category AS cat
  FROM courses
  WHERE category IS NOT NULL AND category <> ''
  ORDER BY category
");

/* ==============================
   FILTERS (WHERE + params)
   ============================== */
$where = "WHERE 1=1";
$params = [];

if ($q !== '') {
  $like = '%' . $q . '%';
  $where .= " AND (
      u.full_name LIKE ? OR c.name LIKE ? OR ca.code LIKE ? OR e.status LIKE ? OR o.start_date LIKE ? OR e.training_date LIKE ?
    )";
  array_push($params, $like, $like, $like, $like, $like, $like);
}

$validStatus = ['reserved','confirmed','attended','no_show','cancelled'];
if ($statusF !== '' && in_array($statusF, $validStatus, true)) {
  $where .= " AND e.status = ?";
  $params[] = $statusF;
}

if ($catF !== '') {
  $where .= " AND c.category = ?";
  $params[] = $catF;
}

/* ==============================
   TOTAL COUNT (for pagination)
   ============================== */
$totalRow = qone("
  SELECT COUNT(*) AS cnt
  FROM enrollments e
  LEFT JOIN users     u ON u.id=e.user_id
  LEFT JOIN offerings o ON o.id=e.offering_id
  LEFT JOIN courses   c ON c.id=o.course_id
  LEFT JOIN campuses  ca ON ca.id=o.campus_id
  $where
", $params);
$total = (int)($totalRow['cnt'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages && $pages > 0) { 
  $page = $pages; 
  $offset = ($page - 1) * $perPage; 
}

/* ==============================
   DATA list (paged + sorted)
   ============================== */
$rows = qall("
  SELECT e.id,
         u.full_name AS student_name,
         c.name AS course_name,
         c.category AS course_category,
         ca.code AS campus,
         o.start_date,
         e.training_date,
         e.status,
         e.created_at
  FROM enrollments e
  LEFT JOIN users     u ON u.id=e.user_id
  LEFT JOIN offerings o ON o.id=e.offering_id
  LEFT JOIN courses   c ON c.id=o.course_id
  LEFT JOIN campuses  ca ON ca.id=o.campus_id
  $where
  ORDER BY $sortCol $dir, e.id DESC
  LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
", $params);

/* Helper to build query strings while preserving filters */
function qs(array $add = []) {
  $base = $_GET;
  foreach ($add as $k=>$v) $base[$k] = $v;
  return 'bookings.php?' . http_build_query(array_filter($base, fn($v) => $v !== null));
}
?>
<!-- ============================== -->
<!-- Toolbar (search + filters + sort + new) -->
<!-- ============================== -->
<div class="toolbar d-flex flex-wrap gap-2 align-items-center">
  <form id="filterForm" class="d-flex flex-wrap gap-2 align-items-center" method="get" action="bookings.php">
    <input class="input form-control" style="max-width:240px" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search bookings…">

    <select name="status" class="form-select">
      <option value="">All status</option>
      <?php foreach ($validStatus as $st): ?>
        <option value="<?= $st ?>" <?= $statusF===$st ? 'selected' : '' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>

    <select name="cat" class="form-select">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): $cat = $c['cat']; ?>
        <option value="<?= htmlspecialchars($cat) ?>" <?= $catF===$cat ? 'selected' : '' ?>>
          <?= htmlspecialchars(ucfirst($cat)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- Sort selector (3 choices only) -->
    <select name="sort" class="form-select">
      <option value="date"    <?= $sortKey==='date'    ? 'selected' : '' ?>>Sort by Date</option>
      <option value="student" <?= $sortKey==='student' ? 'selected' : '' ?>>Sort by Student</option>
      <option value="course"  <?= $sortKey==='course'  ? 'selected' : '' ?>>Sort by Course</option>
    </select>

    <select name="dir" class="form-select">
      <option value="asc"  <?= $dir==='asc'  ? 'selected' : '' ?>>Asc</option>
      <option value="desc" <?= $dir==='desc' ? 'selected' : '' ?>>Desc</option>
    </select>

    <!-- reset to first page when filtering -->
    <input type="hidden" name="page" value="1">
    <button class="btn btn-outline-secondary" type="submit">Apply</button>
  </form>

  <!-- Bulk inline (ban đầu ẩn, sẽ hiện theo JS) -->
  <div id="bulk-inline" class="bulk-inline ms-2" hidden>
    <span class="bulk-count"><span id="bulk-inline-count">0</span> selected</span>
    <button id="bulk-inline-delete" type="button" class="btn" style="background:#ef4444;color:#fff;border:0">Delete</button>
  </div>

  <div class="spacer flex-grow-1"></div>
  <a class="btn primary btn btn-primary" href="<?= qs(['new'=>1]) ?>">Add new</a>
</div>

<?php if (isset($_GET['new']) || $editBk):
  $b = $editBk ?? ['id'=>0,'user_id'=>'','offering_id'=>'','training_date'=>date('Y-m-d'),'status'=>'reserved']; ?>
  <!-- MODAL: render only when new/edit -->
  <div class="vd-modal is-open" id="vd-modal-booking" aria-modal="true" role="dialog">
    <div class="vd-modal__backdrop" data-vd-close></div>
    <div class="vd-modal__card" role="document">
      <div class="vd-modal__header">
        <h3 class="vd-modal__title"><?= $b['id'] ? 'Edit Booking' : 'New Booking' ?></h3>
        <a class="vd-modal__close" href="bookings.php" aria-label="Close">×</a>
      </div>

      <div class="vd-modal__body">
        <form method="post" class="vd-form-grid" autocomplete="off">
          <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">

          <label>Student
            <select name="user_id" class="form-select" required>
              <option value="">-- choose --</option>
              <?php foreach($students as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (int)$s['id']===(int)$b['user_id']?'selected':'' ?>>
                  <?= htmlspecialchars($s['n']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Offering (Course - Campus - Date)
            <select name="offering_id" class="form-select" required>
              <option value="">-- choose --</option>
              <?php foreach($offers as $o): ?>
                <option value="<?= $o['id'] ?>" <?= (int)$o['id']===(int)$b['offering_id']?'selected':'' ?>>
                  <?= htmlspecialchars($o['n']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Training date
            <input type="date" name="training_date" class="form-control" value="<?= htmlspecialchars($b['training_date']) ?>">
          </label>

          <label>Status
            <select name="status" class="form-select">
              <?php foreach(['reserved','confirmed','attended','no_show','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $b['status']===$st?'selected':'' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <div class="vd-form-actions">
            <button class="btn primary btn btn-primary" name="save_booking" value="1">Save</button>
            <a class="btn btn-outline-secondary" href="bookings.php">Cancel</a>
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
    .vd-form-grid>label{display:flex;flex-direction:column;gap:6px}
    @media (max-width:700px){.vd-form-grid{grid-template-columns:1fr}}
    .vd-form-actions{grid-column:1/-1;display:flex;gap:8px}
    .is-open{visibility:visible;opacity:1}
  </style>
  <script>
    (function(){
      const m = document.getElementById('vd-modal-booking');
      function closeOnEsc(e){ if(e.key==='Escape'){ window.location.href='bookings.php'; } }
      if(m){ document.addEventListener('keydown', closeOnEsc); m.querySelectorAll('[data-vd-close]').forEach(x=>x.addEventListener('click', ()=>{ window.location.href='bookings.php'; })); }
    })();
  </script>
<?php endif; ?>

<style>
  /* legacy sort caret */
  .sort-link { text-decoration:none; color:inherit; position:relative; padding-right:14px; }
  .sort-link:after {
    content:''; border:4px solid transparent; border-top-color: currentColor;
    position:absolute; right:2px; top:50%; transform:translateY(-2px) rotate(180deg); opacity:.35;
  }
  .sort-link.asc:after  { transform:translateY(-2px) rotate(0deg); opacity:1; }
  .sort-link.desc:after { transform:translateY(-2px) rotate(180deg); opacity:1; }

  /* Pagination — centered horizontal, square pills, no bullets, no ellipsis */
  .pager-bar{
    display:flex; align-items:center; justify-content:center; gap:.5rem; margin-top:.9rem;
  }
  .pager-bar ul{
    display:flex; gap:.4rem; padding:0; margin:0; list-style:none;
  }
  .pager-bar li a{
    display:inline-flex; align-items:center; justify-content:center;
    width:36px; height:36px; border:1px solid #d0d5dd; border-radius:8px;
    background:#fff; color:#111; font-weight:600; text-decoration:none; transition:.15s;
  }
  .pager-bar li a:hover{ background:#f3f4f6; }
  .pager-bar li.active a{ background:var(--brand,#4f7cff); color:#fff; border-color:var(--brand,#4f7cff); }
  .pager-bar li.disabled a{ opacity:.5; pointer-events:none; }
</style>

<div class="table-wrap table-responsive mt-3">
  <table class="table table-hover align-middle table-sm" id="tbl-bookings">
    <thead>
      <tr>
        <th></th>
        <th>Code</th>
        <th>Student</th>
        <th>Course</th>
        <th>Category</th>
        <th>Campus & Date</th>
        <th>Status</th>
        <th class="text-nowrap">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r):
        $code = 'BKG-' . str_pad($r['id'], 3, '0', STR_PAD_LEFT);
        $name = trim($r['student_name'] ?? '');
        $cls  = match ($r['status']) {'confirmed'=>'success','reserved'=>'warn','cancelled'=>'gray','attended'=>'blue',default=>'dark'};
        $bs   = match ($r['status']) {'confirmed'=>'bg-success','reserved'=>'bg-warning text-dark','cancelled'=>'bg-secondary','attended'=>'bg-info text-dark',default=>'bg-dark'};
        $dateLabel = $r['training_date'] ?: $r['start_date'];
      ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>"></td>
        <td><?= $code ?></td>
        <td><?= htmlspecialchars($name) ?></td>
        <td><?= htmlspecialchars($r['course_name']) ?></td>
        <td><?= htmlspecialchars($r['course_category'] ?? '') ?></td>
        <td><?= htmlspecialchars(($r['campus'] ?? '').' · '.($dateLabel ?? '')) ?></td>
        <td><span class="badge <?= $cls ?> badge rounded-pill <?= $bs ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td class="row-actions text-nowrap">
          <a href="<?= qs(['edit_id'=>$r['id']]) ?>" class="link"
             style="display:inline-flex;align-items:center;padding:.28rem .6rem;border-radius:.5rem;
                    font-weight:700;line-height:1.1;border:1px solid transparent;text-decoration:none;
                    color:#fff;background:var(--brand);box-shadow:inset 0 0 0 999px rgba(0,0,0,.12);">
            Edit
          </a>

          <a href="<?= qs(['delete_id'=>$r['id']]) ?>" class="link danger"
             onclick="return confirm('Delete this booking?')"
             style="display:inline-flex;align-items:center;padding:.28rem .6rem;border-radius:.5rem;
                    font-weight:700;line-height:1.1;border:1px solid transparent;text-decoration:none;
                    color:#fff;background:var(--red);box-shadow:inset 0 0 0 999px rgba(0,0,0,.14);margin-left:.4rem;">
            Delete
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <tr><td colspan="8" class="text-center text-muted">No bookings found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ============================== -->
<!-- Pagination (no bullets, centered, square boxes, no ellipsis) -->
<!-- ============================== -->
<?php if ($pages > 1): ?>
<nav aria-label="Bookings pagination" class="pager-bar">
  <ul>
    <!-- Prev -->
    <li class="<?= $page<=1?'disabled':'' ?>">
      <a href="<?= qs(['page'=>max(1,$page-1)]) ?>">‹</a>
    </li>

    <?php
      $window = 5; $half = 2;
      $start = max(1, $page - $half);
      $end   = min($pages, $page + $half);
      if (($end - $start + 1) < $window) {
        if ($start === 1)       $end   = min($pages, $start + $window - 1);
        elseif ($end === $pages) $start = max(1, $end   - $window + 1);
      }
      for ($p=$start; $p<=$end; $p++):
        $active = $p===$page ? 'active' : '';
    ?>
      <li class="<?= $active ?>"><a href="<?= qs(['page'=>$p]) ?>"><?= $p ?></a></li>
    <?php endfor; ?>

    <!-- Next -->
    <li class="<?= $page>=$pages?'disabled':'' ?>">
      <a href="<?= qs(['page'=>min($pages,$page+1)]) ?>">›</a>
    </li>
  </ul>
</nav>

<?php
  $from = $total ? $offset + 1 : 0;
  $to   = min($total, $offset + $perPage);
?>
<div class="small text-muted text-center mt-1">Showing <?= $from ?>–<?= $to ?> of <?= $total ?> results</div>
<?php endif; ?>

<script>
  // submit filter on Enter in search box
  document.querySelector('input[name="q"]')?.addEventListener('keydown', e=>{
    if(e.key==='Enter'){ e.target.form.submit(); }
  });

  // BULK: show/hide bar ngay khi tick từng cái
  (function(){
    const table = document.getElementById('tbl-bookings');
    const bulk  = document.getElementById('bulk-inline');
    const count = document.getElementById('bulk-inline-count');

    function updateBulk(){
      const checked = table ? table.querySelectorAll('tbody input[type="checkbox"]:checked').length : 0;
      count.textContent = checked;
      if (checked > 0) bulk.hidden = false; else bulk.hidden = true;
    }

    table?.addEventListener('change', updateBulk);
    updateBulk();

    document.getElementById('bulk-inline-delete')?.addEventListener('click', ()=>{
      if(!confirm('Delete selected bookings?')) return;
      const ids = Array.from(table.querySelectorAll('tbody input[type="checkbox"]:checked')).map(cb=>cb.value);
      if(ids.length===0) return;
      const first = ids.shift();
      window.location.href = 'bookings.php?delete_id='+encodeURIComponent(first);
    });
  })();
</script>

<?php require 'footer.php'; ?>
