<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ==========================================================
   Load DB helpers
   ========================================================== */
require_once __DIR__ . '/../includes/db.php';

/* ==========================================================
   AJAX Save / Delete — BEFORE any HTML output
   ========================================================== */
   date_default_timezone_set('Pacific/Auckland');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (ob_get_length()) { ob_clean(); }
  header('Content-Type: application/json; charset=utf-8');

  try {
    if ($_POST['action'] === 'save') {
      $id           = (int)($_POST['id'] ?? 0);
      $campus       = (int)$_POST['campus_id'];
      $course       = (int)$_POST['course_id'];
      $start_date   = $_POST['start_date'] ?? null;     // YYYY-MM-DD
      $start_time   = $_POST['start_time'] ?? null;     // HH:MM
      $duration     = $_POST['duration_hours'] ?: null; // DECIMAL(4,2)
      $repeat_type  = $_POST['repeat_type'] ?? 'none';  // none | monthly_rule

      // Seats min/max
      $seats_min    = isset($_POST['seats_min']) ? max(0, (int)$_POST['seats_min']) : 0;
      $seats_max    = isset($_POST['seats_max']) ? max(0, (int)$_POST['seats_max']) : 0;
      if ($seats_min > $seats_max) { $tmp = $seats_min; $seats_min = $seats_max; $seats_max = $tmp; }

      // Recurrence (multiple weeks)
      $form_weekday = $_POST['rec_weekday'] ?? null;     // Mon..Sun
      $form_weeks   = $_POST['rec_week_of_month'] ?? []; // ['1','3'] ...
      if (!is_array($form_weeks)) $form_weeks = [];
      $week_of_month = count($form_weeks) ? implode(',', array_values(array_unique($form_weeks))) : null;
      $weekday = null;

      if ($repeat_type === 'monthly_rule') {
        $weekday = $form_weekday ?: ($start_date ? date('D', strtotime($start_date)) : null);
        if (!$week_of_month && $start_date) $week_of_month = (string)ceil(date('j', strtotime($start_date)) / 7);
      }

      if ($id > 0) {
        qexec(
          "UPDATE offerings 
             SET campus_id=?, course_id=?, start_date=?, start_time=?, duration_hours=?, 
                 repeat_type=?, weekday=?, week_of_month=?, seats_min=?, seats_max=?, status='scheduled'
           WHERE id=?",
          [$campus,$course,$start_date,$start_time,$duration,
           $repeat_type,$weekday,$week_of_month,$seats_min,$seats_max,$id]
        );
      } else {
        qexec(
          "INSERT INTO offerings
             (campus_id,course_id,start_date,start_time,duration_hours,
              repeat_type,weekday,week_of_month,seats_min,seats_max,status)
           VALUES (?,?,?,?,?,?,?,?,?,?, 'scheduled')",
          [$campus,$course,$start_date,$start_time,$duration,
           $repeat_type,$weekday,$week_of_month,$seats_min,$seats_max]
        );
      }

      echo json_encode(['ok'=>1]); exit;
    }

    if ($_POST['action'] === 'delete') {
      qexec("DELETE FROM offerings WHERE id=?", [(int)$_POST['id']]);
      echo json_encode(['ok'=>1]); exit;
    }

    echo json_encode(['ok'=>0,'err'=>'Unknown action']); exit;

  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>0,'err'=>$e->getMessage()]); exit;
  }
}

/* ==========================================================
   PAGE DATA (render HTML from here)
   ========================================================== */
$PAGE_TITLE  = 'Scheduled';
$BREADCRUMB  = 'Admin / Scheduled';
require 'header.php';

/* include course meta to render multi-day correctly */
$offerings = qall("
  SELECT 
    o.*,
    c.name  AS course_name,
    c.duration_unit   AS course_duration_unit,
    c.duration_hours  AS course_duration_value,
    cp.name AS campus_name,
    cp.code AS campus_code
  FROM offerings o
  JOIN courses  c  ON c.id=o.course_id
  JOIN campuses cp ON cp.id=o.campus_id
  WHERE o.status IS NULL OR o.status!='cancelled'
");

/* helper: nth weekday in month, e.g., 1st Thu, 3rd Mon */
function getNthWeekdayOfMonth($month,$weekday,$nth){
  $ts = strtotime("first $weekday of $month");
  if($nth>1) $ts = strtotime("+".($nth-1)." week",$ts);
  return date('Y-m-d',$ts);
}

/* helper: compute end
   - DAYS: end = 23:59 of last day (start + days - 1)  → phủ đúng số ngày liên tiếp (có T7/CN)
   - HOURS: end = start_time + duration_hours
   FullCalendar treats end as exclusive for all-day, inclusive-like for timed spans.
*/
function computeEnd($startDate, $startTime, $durationHoursOffering, $courseUnit, $courseValue) {
  if ($courseUnit === 'days' && (int)$courseValue >= 1) {
    $days = (int)$courseValue;
    $lastDay = date('Y-m-d', strtotime($startDate . ' + ' . ($days - 1) . ' day'));
    return $lastDay . 'T23:59:00';
  }
  $dur = (float)($durationHoursOffering ?: 0);
  if ($dur > 0 && $startTime) {
    $endTime = date('H:i', strtotime($startTime . " + {$dur} hour"));
    return $startDate . 'T' . $endTime;
  }
  return null;
}

/* Build EVENTS_DATA + state */
$today = (new DateTime('now', new DateTimeZone('Pacific/Auckland')))->format('Y-m-d');
$events = [];

foreach ($offerings as $r) {
  $courseUnit  = $r['course_duration_unit'] ?: 'hours';
  $courseValue = (int)($r['course_duration_value'] ?: 0);

  if ($r['repeat_type'] === 'monthly_rule' && $r['weekday'] && $r['week_of_month']) {
    $weeks = array_filter(explode(',', (string)$r['week_of_month']));
    for ($i=0; $i<3; $i++) { // next 3 months
      $monthShow = date('Y-m', strtotime("+$i month"));
      foreach ($weeks as $nth_raw) {
        $nth = (int)$nth_raw; if ($nth < 1 || $nth > 4) continue;
        $day = getNthWeekdayOfMonth($monthShow, $r['weekday'], $nth);

        $endDT = computeEnd($day, $r['start_time'], $r['duration_hours'], $courseUnit, $courseValue);
        $state = ($day < $today) ? 'past' : (($day === $today) ? 'today' : 'future');

        $events[] = [
          'id'    => (string)$r['id'],
          'title' => $r['course_name'],
          'start' => $day . ($r['start_time'] ? 'T'.$r['start_time'] : ''),
          'end'   => $endDT,
          'extendedProps' => [
            'campus_id'   => (string)$r['campus_id'],
            'campus_code' => strtoupper($r['campus_code'] ?? ''),
            'course_id'   => (string)$r['course_id'],
            'start_date'  => $day,
            'start_time'  => $r['start_time'],
            'duration_hours' => (string)$r['duration_hours'],
            'repeat_type' => 'monthly_rule',
            'seats_min'   => (string)$r['seats_min'],
            'seats_max'   => (string)$r['seats_max'],
            'rec_weekday' => $r['weekday'],
            'rec_week_of_month' => $r['week_of_month'],
            'state'       => $state,
            'course_duration_unit'  => $courseUnit,
            'course_duration_value' => (string)$courseValue
          ]
        ];
      }
    }
  } else {
    $day   = $r['start_date'];
    $endDT = computeEnd($day, $r['start_time'], $r['duration_hours'], $courseUnit, $courseValue);
    $state = ($day < $today) ? 'past' : (($day === $today) ? 'today' : 'future');

    $events[] = [
      'id'    => (string)$r['id'],
      'title' => $r['course_name'],
      'start' => $day . ($r['start_time'] ? 'T'.$r['start_time'] : ''),
      'end'   => $endDT,
      'extendedProps' => [
        'campus_id'   => (string)$r['campus_id'],
        'campus_code' => strtoupper($r['campus_code'] ?? ''),
        'course_id'   => (string)$r['course_id'],
        'start_date'  => $day,
        'start_time'  => $r['start_time'],
        'duration_hours' => (string)$r['duration_hours'],
        'repeat_type' => 'none',
        'seats_min'   => (string)$r['seats_min'],
        'seats_max'   => (string)$r['seats_max'],
        'rec_weekday' => '',
        'rec_week_of_month' => '',
        'state'       => $state,
        'course_duration_unit'  => $courseUnit,
        'course_duration_value' => (string)$courseValue
      ]
    ];
  }
}

/* Mark NEXT (closest future) */
$nextIdx = null; $nextDate = null;
foreach ($events as $i => $ev) {
  $d = substr($ev['start'], 0, 10);
  $st = $ev['extendedProps']['state'] ?? '';
  if ($st === 'future') {
    if ($nextDate === null || $d < $nextDate) { $nextDate = $d; $nextIdx = $i; }
  }
}
if ($nextIdx !== null) {
  $events[$nextIdx]['extendedProps']['state'] = 'next';
}

/* Course meta for hint */
$coursesMeta = qall("SELECT id, name, duration_unit, duration_hours FROM courses ORDER BY name");
$COURSE_META = [];
foreach ($coursesMeta as $cm) {
  $COURSE_META[$cm['id']] = [
    'unit'  => $cm['duration_unit'] ?: 'hours',
    'value' => (int)($cm['duration_hours'] ?: 0),
    'name'  => $cm['name']
  ];
}
?>
<script>
  const EVENTS_DATA  = <?php echo json_encode($events, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
  const COURSE_META  = <?php echo json_encode($COURSE_META, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- ========== HEAD ========== -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
  .toolbar-card { border: 0; border-radius: 1rem; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
  .calendar-card { border: 0; border-radius: 1rem; box-shadow: 0 12px 32px rgba(0,0,0,.08); }
  .fc .fc-toolbar-title { font-size: 1.15rem; }
  .btn-action { font-weight: 600; }
  .legend-dot { width: .75rem; height: .75rem; border-radius: 999px; display:inline-block; margin-right:.4rem; }

  /* Better readability in cells */
  .fc .fc-daygrid-event { padding: 2px 6px; border-radius: 8px; }
  .fc .fc-event-main { white-space: normal; }
  .fc .fc-daygrid-event .fc-event-time { display: none; }
  .fc-tt .tt1 { font-weight: 600; line-height: 1.2; }
  .fc-tt .tt2 { font-size: 0.85em; opacity: .9; line-height: 1.2; }
  .fc .fc-daygrid-day-events { margin: 2px 4px 0; }

  /* STATUS COLORS (chỉ 3 màu) */
  .ev-state-next  { background: #10b981 !important; color: #0b1f16 !important; } /* xanh lá: sắp tới */
  .ev-state-today { background: #f59e0b !important; color: #111827 !important; } /* vàng: hôm nay */
  .ev-state-past  { background: #ef4444 !important; color: #ffffff !important; } /* đỏ: đã qua */
</style>

<div class="container-fluid py-3">

  <!-- Toolbar -->
  <div class="card toolbar-card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Jump to month</label>
          <input type="month" id="monthPicker" class="form-control">
        </div>
        <div class="col-12 col-md-8 d-flex flex-wrap gap-3 justify-content-md-end mt-2 mt-md-0 align-items-center">
          <button type="button" class="btn btn-success btn-action" onclick="openModal()">+ New Class</button>

          <!-- Legend (chỉ còn 3 trạng thái) -->
          <div class="d-flex align-items-center flex-wrap gap-3">
            <span><span class="legend-dot" style="background:#10b981"></span>Next</span>
            <span><span class="legend-dot" style="background:#f59e0b"></span>Today</span>
            <span><span class="legend-dot" style="background:#ef4444"></span>Past</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Calendar -->
  <div class="card calendar-card">
    <div class="card-body">
      <div id="calendar"></div>
    </div>
  </div>
</div>

<!-- MODAL ADD/EDIT -->
<div class="modal fade" id="classModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="classForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="classModalTitle">Add Class</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="class_id">

        <div class="mb-3">
          <label class="form-label">Campus</label>
          <select name="campus_id" class="form-select" required>
            <?php foreach(qall("SELECT id,name FROM campuses ORDER BY name") as $c): ?>
              <option value="<?=$c['id']?>"><?=$c['name']?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-1">
          <label class="form-label">Course</label>
          <select name="course_id" id="course_id" class="form-select" required>
            <?php foreach(qall("SELECT id,name FROM courses WHERE status='active' ORDER BY name") as $c): ?>
              <option value="<?=$c['id']?>"><?=$c['name']?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <small id="courseHint" class="text-muted"></small>
        </div>

        <div class="mb-3">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" id="start_date" class="form-control" required>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Start Time</label>
            <input type="time" name="start_time" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Duration (hours)</label>
            <input type="number" step="0.25" min="0.5" name="duration_hours" id="duration_hours" class="form-control" placeholder="e.g. 4">
          </div>
        </div>

        <!-- Seats min/max -->
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Seats (min)</label>
            <input type="number" name="seats_min" class="form-control" min="0" value="0" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Seats (max)</label>
            <input type="number" name="seats_max" class="form-control" min="1" value="20" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Repeat Type</label>
          <select name="repeat_type" id="repeat_type" class="form-select">
            <option value="none">One-off</option>
            <option value="monthly_rule">Repeat next months</option>
          </select>
        </div>

        <!-- Recurrence -->
        <div id="recurrenceBox" class="border rounded p-3" style="display:none">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Week(s) of month</label>
              <div class="d-flex flex-wrap gap-2">
                <?php for($i=1;$i<=4;$i++): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="rec_week_of_month[]" value="<?=$i?>" id="rec_w<?=$i?>">
                    <label class="form-check-label" for="rec_w<?=$i?>"><?=$i?><?=($i==1?'st':($i==2?'nd':($i==3?'rd':'th')))?></label>
                  </div>
                <?php endfor; ?>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Weekday</label>
              <select name="rec_weekday" id="rec_weekday" class="form-select">
                <option value="">-- choose --</option>
                <option value="Mon">Monday</option>
                <option value="Tue">Tuesday</option>
                <option value="Wed">Wednesday</option>
                <option value="Thu">Thursday</option>
                <option value="Fri">Friday</option>
                <option value="Sat">Saturday</option>
                <option value="Sun">Sunday</option>
              </select>
            </div>
          </div>
          <small class="text-muted">If left blank, the system will infer from the Start Date.</small>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-danger me-auto" id="btnDelete" style="display:none">Delete</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- FULLCALENDAR -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const calEl = document.getElementById('calendar');
  const SRC = EVENTS_DATA;

  const calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    height: '80vh',
    eventDisplay: 'block',
    dayMaxEventRows: 3,
    expandRows: true,

    // Title + (CAMPUS · time · 3d/4h)
    eventContent: function(arg) {
      const p = arg.event.extendedProps || {};
      const wrap = document.createElement('div');
      wrap.className = 'fc-tt';

      const t1 = document.createElement('div');
      t1.className = 'tt1';
      t1.textContent = (arg.event.title || '');

      const parts = [];
      if (p.campus_code) parts.push(String(p.campus_code).toUpperCase());
      if (p.start_time) parts.push(String(p.start_time).slice(0,5));
      if (p.course_duration_unit === 'days' && parseInt(p.course_duration_value||0) >= 1) {
        parts.push(String(parseInt(p.course_duration_value)) + 'd');
      } else if (p.duration_hours) {
        parts.push(String(parseFloat(p.duration_hours)) + 'h');
      }
      const t2 = document.createElement('div');
      t2.className = 'tt2';
      t2.textContent = parts.join(' · ');

      wrap.appendChild(t1);
      wrap.appendChild(t2);
      return { domNodes: [wrap] };
    },

    // Only 3 colors + state class
    events: SRC.map(e => {
      const st  = e.extendedProps?.state || '';
      const stCls = st ? ('ev-state-' + st) : '';

      let color = '#10b981';           // next = green
      if (st === 'today') color = '#f59e0b'; // today = yellow
      if (st === 'past')  color = '#ef4444'; // past = red

      return { ...e, color, classNames: [stCls] };
    }),

    dateClick: function(info) { openModal('', info.dateStr); },

    eventClick: function(info) {
      const ev = info.event, p = ev.extendedProps || {};
      document.querySelector('select[name="campus_id"]').value = p.campus_id ?? '';
      const courseSel = document.getElementById('course_id');
      courseSel.value = p.course_id ?? '';

      applyCourseHintAndDuration(courseSel.value);

      document.querySelector('input[name="start_date"]').value  = (p.start_date || ev.startStr || '').slice(0,10);
      document.querySelector('input[name="start_time"]').value  = (p.start_time || (ev.start ? ev.start.toTimeString().slice(0,5) : '')) || '';
      document.getElementById('duration_hours').value           = p.duration_hours ?? '';
      document.querySelector('input[name="seats_min"]').value   = p.seats_min ?? 0;
      document.querySelector('input[name="seats_max"]').value   = p.seats_max ?? 20;

      document.getElementById('repeat_type').value = p.repeat_type || 'none';
      toggleRecurrenceBox();

      clearRecWeekChecks();
      const recWeeks = (p.rec_week_of_month || '').split(',').filter(Boolean);
      recWeeks.forEach(w => { const cb = document.getElementById('rec_w'+w.trim()); if (cb) cb.checked = true; });
      document.getElementById('rec_weekday').value = p.rec_weekday || '';

      document.getElementById('class_id').value = ev.id;
      document.getElementById('classModalTitle').textContent = 'Edit Class';

      const btnDel = document.getElementById('btnDelete');
      btnDel.style.display = 'inline-block';
      btnDel.onclick = function(){
        if(!confirm('Delete this class?')) return;
        fetch('', {
          method:'POST',
          credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
          body:new URLSearchParams({ action:'delete', id: ev.id })
        }).then(r=>r.json()).then(j=>{
          if(!j.ok){ alert(j.err||'Delete failed'); return; }
          location.reload();
        }).catch(e=>{ console.error(e); alert('Network error'); });
      };

      new bootstrap.Modal('#classModal').show();
    }
  });
  calendar.render();

  // Month picker
  const monthPicker = document.getElementById('monthPicker');
  const setPickerToCurrent = () => {
    const d = calendar.getDate();
    const ym = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
    monthPicker.value = ym;
  };
  setPickerToCurrent();
  monthPicker.addEventListener('change', function () {
    if (!this.value) return;
    calendar.gotoDate(this.value + '-01');
  });

  // Submit form (Save)
  const form = document.getElementById('classForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = new FormData(form);
    data.append('action', 'save');
    fetch('', {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: {'X-Requested-With':'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(j => {
      if (!j.ok) { console.error(j.err); alert(j.err || 'Save failed'); return; }
      location.reload();
    })
    .catch(err => { console.error(err); alert('Network error'); });
  });

  // Toggle Recurrence UI
  const repeatSel = document.getElementById('repeat_type');
  repeatSel.addEventListener('change', toggleRecurrenceBox);
  function toggleRecurrenceBox() {
    const box = document.getElementById('recurrenceBox');
    const val = repeatSel.value;
    box.style.display = (val === 'monthly_rule') ? 'block' : 'none';
    if (val !== 'monthly_rule') { clearRecWeekChecks(); document.getElementById('rec_weekday').value = ''; }
    else {
      const sd = document.getElementById('start_date').value;
      if (sd) {
        const d = new Date(sd + 'T00:00:00');
        const weekdayMap = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        const wd = weekdayMap[d.getUTCDay()];
        const wom = Math.min(4, Math.max(1, Math.ceil(d.getUTCDate() / 7)));
        if (!document.getElementById('rec_weekday').value) document.getElementById('rec_weekday').value = wd;
        if (![...document.querySelectorAll('input[name="rec_week_of_month[]"]:checked')].length) {
          const cb = document.getElementById('rec_w'+wom); if (cb) cb.checked = true;
        }
      }
    }
  }
  function clearRecWeekChecks() {
    document.querySelectorAll('input[name="rec_week_of_month[]"]').forEach(cb => cb.checked = false);
  }

  // Auto-hint + duration theo course
  const courseSel = document.getElementById('course_id');
  courseSel.addEventListener('change', function(){ applyCourseHintAndDuration(this.value); });
  applyCourseHintAndDuration(courseSel.value);

  function applyCourseHintAndDuration(courseId){
    const hint = document.getElementById('courseHint');
    const durInput = document.getElementById('duration_hours');
    const meta = COURSE_META[courseId] || null;
    if (!meta) { hint.textContent=''; return; }
    if (meta.unit === 'days' && parseInt(meta.value||0) >= 1) {
      hint.textContent = `This course spans ${parseInt(meta.value)} consecutive day(s).`;
      durInput.placeholder = 'optional for hours-based courses';
    } else {
      hint.textContent = `Default duration: ${parseFloat(meta.value||0)} hour(s).`;
      if (!durInput.value) durInput.value = parseFloat(meta.value||0);
    }
  }

  // Open modal (new)
  window.openModal = (id = '', date = '') => {
    form.reset();
    document.getElementById('class_id').value = id || '';
    document.getElementById('classModalTitle').textContent = id ? 'Edit Class' : 'Add Class';
    if (date) document.getElementById('start_date').value = date;
    document.getElementById('btnDelete').style.display = id ? 'inline-block' : 'none';
    document.getElementById('repeat_type').value = 'none';
    toggleRecurrenceBox();
    applyCourseHintAndDuration(document.getElementById('course_id').value);
    new bootstrap.Modal('#classModal').show();
  };
});
</script>

<?php require 'footer.php'; ?>
