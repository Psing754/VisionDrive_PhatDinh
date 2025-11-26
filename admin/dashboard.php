<?php
$PAGE_TITLE = 'Dashboard';
$BREADCRUMB = 'Admin / Dashboard';

require_once __DIR__ . '/../includes/db.php';
require_once 'auth.php';

/* ==============================
   XLSX helper (no external libs) — moved ABOVE bulk actions
   ============================== */
if (!function_exists('vd_export_xlsx')) {
  function vd_export_xlsx(string $filename, string $sheetTitle, array $headerRow, array $dataRows, array $colWidths = []): void {
    if (!class_exists('ZipArchive')) {
      http_response_code(500);
      echo 'ZipArchive is required for XLSX export.'; exit;
    }

    // 1-based column index -> A, B, ..., AA
    $colLetter = function($i){
      $s=''; while($i>0){ $m=($i-1)%26; $s=chr(65+$m).$s; $i=(int)(($i-$m)/26); } return $s;
    };
    // Build <c> cell XML
    $cellXml = function($r, $c, $v, $bold=false, $isNumber=false) use ($colLetter){
      $ref = $colLetter($c).$r;
      if ($v === null) return "<c r=\"$ref\"/>";
      $s = $bold ? ' s="1"' : '';
      if ($isNumber) {
        $v = is_numeric($v) ? ($v+0) : 0;
        return "<c r=\"$ref\" t=\"n\"$s><v>$v</v></c>";
      } else {
        $v = htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1);
        return "<c r=\"$ref\" t=\"inlineStr\"$s><is><t xml:space=\"preserve\">$v</t></is></c>";
      }
    };

    // Build rows XML
    $rowsXml = '';
    $maxCols = max(count($headerRow), ...array_map('count', $dataRows ?: [[]]));
    $rowIndex = 1;

    // Header row (bold)
    $cells = [];
    for ($c=1; $c<=$maxCols; $c++) { $cells[] = $cellXml($rowIndex, $c, $headerRow[$c-1] ?? '', true, false); }
    $rowsXml .= "<row r=\"$rowIndex\" spans=\"1:$maxCols\">".implode('', $cells)."</row>";
    $rowIndex++;

    // Data rows
    foreach ($dataRows as $r) {
      $cells = [];
      for ($c=1; $c<=$maxCols; $c++) {
        $val = $r[$c-1] ?? '';
        $isNum = is_int($val) || is_float($val);
        $cells[] = $cellXml($rowIndex, $c, $val, false, $isNum);
      }
      $rowsXml .= "<row r=\"$rowIndex\" spans=\"1:$maxCols\">".implode('', $cells)."</row>";
      $rowIndex++;
    }

    $dim = "A1:".$colLetter($maxCols).($rowIndex-1);

    // Optional column widths
    $colsXml = '';
    if ($colWidths) {
      $pieces = [];
      foreach ($colWidths as $idx => $w) {
        $w = max(6, (float)$w);
        $pieces[] = "<col min=\"$idx\" max=\"$idx\" width=\"$w\" customWidth=\"1\"/>";
      }
      $colsXml = "<cols>".implode('', $pieces)."</cols>";
    }

    // OOXML parts
    $contentTypes = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;

    $relsRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;

    $safeTitle = htmlspecialchars($sheetTitle, ENT_QUOTES | ENT_XML1);
    $workbook = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="{$safeTitle}" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;

    $workbookRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

    // Basic styles: xf0 normal, xf1 bold header
    $styles = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Segoe UI"/><sz val="10"/></font>
    <font><b/><name val="Segoe UI"/><sz val="10"/></font>
  </fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf xfId="0" fontId="0" fillId="0" borderId="0" applyFont="1"/>
    <xf xfId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>
XML;

    $sheet1 = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <dimension ref="{$dim}"/>
  {$colsXml}
  <sheetData>
    {$rowsXml}
  </sheetData>
  <pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>
</worksheet>
XML;

    // Zip & output
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addEmptyDir('_rels');
    $zip->addFromString('_rels/.rels', $relsRels);
    $zip->addEmptyDir('xl');
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addEmptyDir('xl/_rels');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addEmptyDir('xl/worksheets');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
    $zip->close();

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
  }
}

/* ==============================
   DELETE SINGLE BOOKING
   ============================== */
if (isset($_GET['delete_booking_id'])) {
  $id = (int) $_GET['delete_booking_id'];
  qexec("DELETE FROM enrollments WHERE id=?", [$id]);
  header('Location: dashboard.php?msg=Deleted+booking+' . $id);
  exit;
}

/* ==============================
   BULK ACTIONS (UPDATE / EXPORT)
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['ids'])) {
  $ids = array_map('intval', (array)$_POST['ids']);
  $ph  = implode(',', array_fill(0, count($ids), '?'));

  /* ---- BULK UPDATE STATUS ---- */
  if ($_POST['bulk_action'] === 'update') {
    $newStatus = trim(strtolower($_POST['status'] ?? ''));
    $allowed   = ['reserved','confirmed','attended','no_show','cancelled'];
    if (!in_array($newStatus, $allowed, true)) {
      header('Location: dashboard.php?msg=Invalid+status'); exit;
    }
    $params = $ids;
    array_unshift($params, $newStatus);
    qexec("UPDATE enrollments SET status=? WHERE id IN ($ph)", $params);
    header('Location: dashboard.php?msg=Updated+'.count($ids).'+bookings');
    exit;
  }

  /* ---- BULK EXPORT XLSX ---- */
  if ($_POST['bulk_action'] === 'export') {
    // Fetch data
    $rows = qall("
      SELECT e.id,
             u.full_name AS student_name,
             c.name      AS course_name,
             o.start_date,
             e.status
      FROM enrollments e
      LEFT JOIN users     u ON u.id=e.user_id
      LEFT JOIN offerings o ON o.id=e.offering_id
      LEFT JOIN courses   c ON c.id=o.course_id
      WHERE e.id IN ($ph)
      ORDER BY e.id ASC
    ", $ids);

    $head = ['Code','Student','Course','Date','Status'];
    $data = [];
    foreach ($rows as $r) {
      $code = 'BKG-'.str_pad($r['id'], 3, '0', STR_PAD_LEFT);
      $name = trim($r['student_name'] ?? '');
      $data[] = [
        $code,
        $name,
        (string)$r['course_name'],
        (string)$r['start_date'],
        (string)$r['status'],
      ];
    }

    $colWidths = [1=>14, 2=>28, 3=>48, 4=>14, 5=>12];
    vd_export_xlsx('bookings_export_'.date('Ymd_His').'.xlsx', 'Bookings', $head, $data, $colWidths);
  }
}

/* ==============================
   PAGE DATA
   ============================== */
require 'header.php';

$totalBookings = (qone("SELECT COUNT(*) AS c FROM enrollments")['c'] ?? 0);
$totalUsers    = (qone("SELECT COUNT(*) AS c FROM users WHERE role='student'")['c'] ?? 0);
$totalCourses  = (qone("SELECT COUNT(*) AS c FROM courses")['c'] ?? 0);

$recent = qall("
  SELECT e.id,
         u.full_name AS student_name,
         c.name AS course_name,
         o.start_date,
         e.status
  FROM enrollments e
  LEFT JOIN users     u ON u.id=e.user_id
  LEFT JOIN offerings o ON o.id=e.offering_id
  LEFT JOIN courses   c ON c.id=o.course_id
  ORDER BY e.created_at DESC
  LIMIT 5
");
?>

<div class="cards row g-3">
  <div class="col-12 col-md-4"><div class="card h-100"><div class="card-label">Total Bookings</div><div class="card-value"><?= $totalBookings ?></div></div></div>
  <div class="col-12 col-md-4"><div class="card h-100"><div class="card-label">Learners</div><div class="card-value"><?= $totalUsers ?></div></div></div>
  <div class="col-12 col-md-4"><div class="card h-100"><div class="card-label">Active Courses</div><div class="card-value"><?= $totalCourses ?></div></div></div>
</div>

<section class="section mt-3">
  <div class="section-head d-flex flex-wrap align-items-center gap-2">
    <h2 class="m-0">Recent Bookings</h2>
    <div class="section-actions ms-auto d-flex flex-wrap gap-2">
      <div id="bulk-inline" class="bulk-inline" hidden>
        <span class="bulk-count"><span id="bulk-inline-count">0</span> selected</span>
        <button id="bulk-inline-delete" type="button" class="btn" style="background:#ef4444;color:#fff;border:0">Delete</button>
      </div>

      <button class="btn btn btn-secondary" id="btn-bulk-update" disabled>Update Status</button>
      <button class="btn btn btn-outline-primary" id="btn-bulk-export" disabled>Export</button>
    </div>
  </div>

  <form id="bulk-form" method="post" style="display:none">
    <input type="hidden" name="bulk_action" value="">
    <input type="hidden" name="status" value="">
  </form>

  <div class="table-wrap table-responsive mt-2">
    <table class="table table-hover align-middle table-sm" id="recent-bookings">
      <thead>
        <tr>
          <th><input type="checkbox" data-selectall="recent-bookings"></th>
          <th>Code</th>
          <th>Student</th>
          <th>Course</th>
          <th>Date</th>
          <th>Status</th>
          <th class="text-nowrap">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r):
          $code = 'BKG-' . str_pad($r['id'], 3, '0', STR_PAD_LEFT);
          $name = trim($r['student_name'] ?? '');
          $cls  = ($r['status']=='confirmed'?'success':($r['status']=='reserved'?'warn':($r['status']=='cancelled'?'gray':($r['status']=='attended'?'blue':'dark'))));
          $bsBadge = ($r['status']=='confirmed'?'bg-success':($r['status']=='reserved'?'bg-warning text-dark':($r['status']=='cancelled'?'bg-secondary':($r['status']=='attended'?'bg-info text-dark':'bg-dark'))));
        ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
            <td><?= $code ?></td>
            <td><?= htmlspecialchars($name) ?></td>
            <td><?= htmlspecialchars($r['course_name']) ?></td>
            <td><?= htmlspecialchars($r['start_date']) ?></td>
            <td><span class="badge <?= $cls ?> badge rounded-pill <?= $bsBadge ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td class="row-actions text-nowrap">
              <a href="bookings.php?edit_id=<?= $r['id'] ?>" class="link">Edit</a>
              <a href="dashboard.php?delete_booking_id=<?= $r['id'] ?>" class="link danger" onclick="return confirm('Delete this booking?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<style>
  .badge.success{background:#16a34a!important;color:#fff}
  .badge.warn{background:#facc15!important;color:#000}
  .badge.gray{background:#9ca3af!important;color:#fff}
  .badge.blue{background:#3b82f6!important;color:#fff}
  .badge.dark{background:#1f2937!important;color:#fff}
</style>

<script>
// ==== Only for the two buttons Update / Export on Dashboard ====
(function(){
  var tbl         = document.getElementById('recent-bookings');
  var master      = tbl ? tbl.querySelector('thead input[type="checkbox"][data-selectall]') : null;
  var btnUpdate   = document.getElementById('btn-bulk-update');
  var btnExport   = document.getElementById('btn-bulk-export');
  var bulkInline  = document.getElementById('bulk-inline');
  var bulkCountEl = document.getElementById('bulk-inline-count');
  var bulkForm    = document.getElementById('bulk-form');

  if(!tbl || !btnUpdate || !btnExport || !bulkForm) return;

  function getCheckedCbs(){
    return Array.prototype.slice.call(
      tbl.querySelectorAll('tbody input[name="ids[]"]:checked')
    );
  }

  function setEnabled(el, on){
    if(!el) return;
    el.disabled = !on;
    if(on) el.removeAttribute('disabled'); else el.setAttribute('disabled','disabled');
  }

  function refreshUI(){
    var n = getCheckedCbs().length;
    setEnabled(btnUpdate, n>0);
    setEnabled(btnExport, n>0);
    if(bulkInline){
      bulkInline.hidden = (n===0);
      if(bulkCountEl) bulkCountEl.textContent = String(n||0);
    }
  }

  // Header select-all (only affects this table)
  if(master){
    master.addEventListener('change', function(){
      var st = !!master.checked;
      tbl.querySelectorAll('tbody input[name="ids[]"]').forEach(function(cb){
        cb.checked = st;
        var tr = cb.closest('tr'); if(tr) tr.classList.toggle('selected', st);
      });
      refreshUI();
    });
  }

  // Row checkbox change
  tbl.addEventListener('change', function(e){
    if(e.target && e.target.matches('tbody input[name="ids[]"]')){
      var tr = e.target.closest('tr'); if(tr) tr.classList.toggle('selected', e.target.checked);
      refreshUI();
    }
  });

  // Inject ids[] into hidden form
  function setHiddenIds(ids){
    bulkForm.querySelectorAll('input[name="ids[]"]').forEach(function(n){ n.remove(); });
    ids.forEach(function(id){
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
      bulkForm.appendChild(inp);
    });
  }

  // Ensure submit back to this page
  if(!bulkForm.getAttribute('action')){
    bulkForm.setAttribute('action', location.pathname);
  }

  // ---- UPDATE STATUS ----
  btnUpdate.addEventListener('click', function(ev){
    if(btnUpdate.disabled) return;
    var ids = getCheckedCbs().map(function(cb){ return cb.value; });
    if(!ids.length) return;

    var allowed = ['reserved','confirmed','attended','no_show','cancelled'];
    var status = window.prompt('Enter new status:\n' + allowed.join(' / '), 'confirmed');
    if(!status) return;
    status = status.trim().toLowerCase();
    if(allowed.indexOf(status) === -1){ alert('Invalid status'); return; }

    bulkForm.querySelector('[name="bulk_action"]').value = 'update';
    bulkForm.querySelector('[name="status"]').value = status;
    setHiddenIds(ids);
    bulkForm.submit();
  });

  // ---- EXPORT XLSX ----
  btnExport.addEventListener('click', function(ev){
    if(btnExport.disabled) return;
    var ids = getCheckedCbs().map(function(cb){ return cb.value; });
    if(!ids.length) return;

    bulkForm.querySelector('[name="bulk_action"]').value = 'export';
    bulkForm.querySelector('[name="status"]').value = '';
    setHiddenIds(ids);
    bulkForm.submit();
  });

  // Init
  refreshUI();
})();
</script>

<?php require 'footer.php'; ?>
