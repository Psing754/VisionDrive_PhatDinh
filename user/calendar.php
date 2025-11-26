<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['course_id']) || empty($_SESSION['campus_id'])) {
    header("Location: /user/course_selection.php");
    exit;
}

$course_id = (int)$_SESSION['course_id'];
$campus_id = (int)$_SESSION['campus_id'];

$errors = [];

/* -------------------------------------------------
   Handle submit (user clicked Continue)
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offering_id'])) {
    $offering_id   = (int)($_POST['offering_id'] ?? 0);
    $training_date = $_POST['training_date'] ?? '';

    if (!$offering_id || !$training_date) {
        $errors['offering'] = "Please select a training date.";
    } else {
        $_SESSION['offering_id']   = $offering_id;
        $_SESSION['training_date'] = $training_date;
        header("Location: /user/details.php");
        exit;
    }
}

/* -------------------------------------------------
   Decide which month/year to display
------------------------------------------------- */
$targetYear  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$targetMonth = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');

if ($targetYear < 2024)  $targetYear = (int)date('Y');
if ($targetYear > 2100)  $targetYear = 2100;
if ($targetMonth < 1)    $targetMonth = 1;
if ($targetMonth > 12)   $targetMonth = 12;

$prevYear  = $targetYear;
$prevMonth = $targetMonth - 1;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear  = $targetYear - 1; }

$nextYear  = $targetYear;
$nextMonth = $targetMonth + 1;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear  = $targetYear + 1; }

/* -------------------------------------------------
   Pull offerings for this course+campus
------------------------------------------------- */
$sql = "
SELECT 
  o.id,
  o.start_date,
  o.start_time,
  o.duration_hours,
  o.seats_max,
  o.status,
  o.repeat_type,
  o.weekday,
  o.week_of_month,
  cam.code AS campus_code
FROM offerings o
JOIN campuses cam ON cam.id = o.campus_id
WHERE o.course_id = :course_id
  AND o.campus_id = :campus_id
  AND o.status IN ('scheduled','full')
ORDER BY o.start_date ASC, o.start_time ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':course_id' => $course_id, ':campus_id' => $campus_id]);
$offerings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   Seats counting per (offering_id, training_date)
------------------------------------------------- */
$bookedRows = qall("
  SELECT offering_id, training_date, COUNT(*) AS booked
  FROM enrollments
  WHERE status <> 'cancelled'
  GROUP BY offering_id, training_date
");
$bookedMap = [];
foreach ($bookedRows as $br) {
    $bookedMap[$br['offering_id'].'|'.$br['training_date']] = (int)$br['booked'];
}

/* -------------------------------------------------
   Helpers
------------------------------------------------- */
function weekdayToNumber($wd) {
    switch ($wd) {
        case 'Sun': return 0; case 'Mon': return 1; case 'Tue': return 2;
        case 'Wed': return 3; case 'Thu': return 4; case 'Fri': return 5;
        case 'Sat': return 6;
    }
    return null;
}

function getNthWeekdayOfMonthExact($year, $month, $weekdayNum, $nth) {
    $daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
    $count = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $ts = mktime(0,0,0,$month,$d,$year);
        if ((int)date('w',$ts) === $weekdayNum) {
            $count++;
            if ($count == $nth) {
                return date('Y-m-d',$ts);
            }
        }
    }
    return null;
}
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* -------------------------------------------------
   Expand offerings into concrete slots ONLY for the viewed month
------------------------------------------------- */
$instancesByDate = []; // 'YYYY-MM-DD' => [ {slot...}, ... ]

foreach ($offerings as $off) {
    $offId       = (int)$off['id'];
    $timeLabel   = $off['start_time'] ? substr($off['start_time'],0,5) : 'TBA';
    $durLabel    = $off['duration_hours'] ? ($off['duration_hours'].'h') : '';
    $campusCode  = $off['campus_code'];
    $seatsMax    = (int)$off['seats_max'];

    $repeatType  = $off['repeat_type'] ?? 'none';
    $weekday     = $off['weekday'] ?? null;
    $weekOfMonth = $off['week_of_month'] ?? '';
    $baseDate    = $off['start_date'];

    $runDates = [];

    if ($repeatType === 'monthly_rule' && $weekday && $weekOfMonth) {
        $weekdayNum = weekdayToNumber($weekday);
        if ($weekdayNum !== null) {
            $nthList = array_filter(array_map('trim', explode(',', $weekOfMonth)));
            foreach ($nthList as $nthStr) {
                $nth = (int)$nthStr;
                if ($nth >= 1 && $nth <= 4) {
                    $calcDate = getNthWeekdayOfMonthExact($targetYear, $targetMonth, $weekdayNum, $nth);
                    if ($calcDate) $runDates[] = $calcDate;
                }
            }
        }
    } else {
        if (!empty($baseDate)) {
            $y = (int)substr($baseDate,0,4);
            $m = (int)substr($baseDate,5,2);
            if ($y === $targetYear && $m === $targetMonth) $runDates[] = $baseDate;
        }
    }

    foreach ($runDates as $d) {
        $key       = $offId.'|'.$d;
        $booked    = $bookedMap[$key] ?? 0;
        $seatsLeft = max(0, $seatsMax - $booked);

        if (!isset($instancesByDate[$d])) $instancesByDate[$d] = [];
        $instancesByDate[$d][] = [
            'offering_id'  => $offId,
            'date'         => $d,
            'time'         => $timeLabel,
            'duration'     => $durLabel,
            'seats_left'   => $seatsLeft,
            'campus_code'  => $campusCode
        ];
    }
}

/* -------------------------------------------------
   Build calendar grid
------------------------------------------------- */
$firstOfMonthTs = mktime(0,0,0,$targetMonth,1,$targetYear);
$firstWeekday   = (int)date('w', $firstOfMonthTs); // 0=Sun..6=Sat
$daysInMonth    = (int)date('t', $firstOfMonthTs);

$hasAnyThisMonth = false;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $testDate = sprintf('%04d-%02d-%02d',$targetYear,$targetMonth,$d);
    if (!empty($instancesByDate[$testDate])) { $hasAnyThisMonth = true; break; }
}

$today = date('Y-m-d');

require_once __DIR__ . '/header_user.php';
?>

<section class="step-wrapper calendar-step">
  <h1 class="step-title">Please choose the training date</h1>
  <p class="step-hint">Select one available class day.</p>

  <?php if (!empty($errors['offering'])): ?>
    <div class="form-error" role="alert"><?php echo h($errors['offering']); ?></div>
  <?php endif; ?>

  <!-- Tiny overrides to wrap slot content & show full info -->
  <style>
    /* allow slots to grow and wrap text */
    .calendar-grid .cal-slots { max-height: none; overflow: visible; }
    .calendar-grid .slot-line { max-height: none; overflow: visible; align-items: flex-start; }
    .calendar-grid .slot-top, .calendar-grid .slot-meta { white-space: normal; }
    /* dim past days */
    .calendar-cell.past { opacity: .6; }
    .slot-line.past { cursor: not-allowed; }
  </style>

  <div class="calendar-toolbar" role="navigation" aria-label="Calendar navigation">
    <a class="cal-nav-btn" href="?y=<?php echo $prevYear; ?>&m=<?php echo $prevMonth; ?>" aria-label="Previous month">‹ Prev</a>
    <div class="cal-current"><?php echo h(date('F Y', $firstOfMonthTs)); ?></div>
    <a class="cal-nav-btn" href="?y=<?php echo $nextYear; ?>&m=<?php echo $nextMonth; ?>" aria-label="Next month">Next ›</a>

    <div class="cal-picker">
      <label for="gotoMonth" class="visually-hidden">Jump to month</label>
      <input type="month" id="gotoMonth"
        value="<?php echo $targetYear . '-' . str_pad($targetMonth,2,'0',STR_PAD_LEFT); ?>"
        aria-label="Jump to any month">
    </div>
  </div>

  <?php if (!$hasAnyThisMonth): ?>
    <p>No classes available in <?php echo h(date('F Y',$firstOfMonthTs)); ?> for this course at this campus.</p>
    <div class="calendar-actions">
      <a class="btn-secondary" href="/user/course_selection.php">Back</a>
      <button type="button" class="btn-primary" disabled>Continue</button>
    </div>

  <?php else: ?>

    <form method="post" action="/user/calendar.php" class="calendar-form" aria-label="Training calendar">
      <div class="calendar-grid" role="grid" aria-label="Calendar dates for selected month">
        <div class="calendar-dow" role="columnheader">Su</div>
        <div class="calendar-dow" role="columnheader">Mo</div>
        <div class="calendar-dow" role="columnheader">Tu</div>
        <div class="calendar-dow" role="columnheader">We</div>
        <div class="calendar-dow" role="columnheader">Th</div>
        <div class="calendar-dow" role="columnheader">Fr</div>
        <div class="calendar-dow" role="columnheader">Sa</div>

        <?php
        for ($i = 0; $i < $firstWeekday; $i++) {
            echo '<div class="calendar-cell empty" role="gridcell" aria-disabled="true"></div>';
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $thisDate = sprintf('%04d-%02d-%02d',$targetYear,$targetMonth,$day);
            $isPastDay = ($thisDate < $today);

            echo '<div class="calendar-cell'.($isPastDay?' past':'').'" role="gridcell">';
            echo   '<div class="cal-daynum">'.$day.'</div>';

            if (!empty($instancesByDate[$thisDate])) {
                echo '<div class="cal-slots">';
                foreach ($instancesByDate[$thisDate] as $slot) {
                    $offeringId = (int)$slot['offering_id'];
                    $timeLabel  = $slot['time'] ? h($slot['time']) : 'TBA';
                    $durLabel   = $slot['duration'] ? h($slot['duration']) : '';
                    $seatsLeft  = (int)$slot['seats_left'];
                    $isFull     = ($seatsLeft <= 0);
                    $campusCode = h($slot['campus_code']);

                    // Badge: add Past when thisDate < today
                    if ($isPastDay) {
                        $badge = '<span class="tag-full">Past</span>';
                    } else {
                        $badge = $isFull ? '<span class="tag-full">Full</span>'
                                         : '<span class="tag-seat">'.$seatsLeft.' left</span>';
                    }

                    // Disable radio if past OR full
                    $disabledAttr = ($isPastDay || $isFull) ? 'disabled aria-disabled="true"' : '';

                    echo '<label class="slot-line '.($isFull?'full ':'').($isPastDay?'past':'').'">';

                    echo '<input type="radio" name="offering_id" value="'.$offeringId.'" '
                       . $disabledAttr
                       . ' aria-label="Class on '.$thisDate.' '.$timeLabel.'">';

                    echo '<div class="slot-info">';
                    echo   '<div class="slot-top">'.$campusCode.' · '.$timeLabel.($durLabel ? ' · '.$durLabel : '').'</div>';
                    echo   '<div class="slot-meta">'.$badge.'</div>';
                    echo '</div>';

                    echo '<input type="hidden" name="training_date_for_'.$offeringId.'" value="'.$thisDate.'">';

                    echo '</label>';
                }
                echo '</div>';
            } else {
                echo '<div class="cal-no-slot"></div>';
            }

            echo '</div>';
        }
        ?>
      </div>

      <input type="hidden" name="training_date" id="training_date_final" value="">

      <div class="calendar-actions">
        <a class="btn-secondary" href="/user/course_selection.php">Back</a>
        <button type="submit" class="btn-primary">Continue</button>
      </div>
    </form>

    <script>
    // copy selected slot's actual date to hidden input
    document.addEventListener('DOMContentLoaded', function(){
      const form = document.querySelector('.calendar-form');
      if (!form) return;
      form.addEventListener('change', function(e){
        if (e.target && e.target.name === 'offering_id' && !e.target.disabled) {
          const chosenId = e.target.value;
          const hiddenDateInput = form.querySelector('input[name="training_date_for_'+chosenId+'"]');
          const finalField = form.querySelector('#training_date_final');
          if (hiddenDateInput && finalField) {
            finalField.value = hiddenDateInput.value;
          }
        }
      });
    });

    // month picker navigation
    document.addEventListener('DOMContentLoaded', function(){
      const picker = document.getElementById('gotoMonth');
      if (!picker) return;
      picker.addEventListener('change', function(){
        if (!this.value) return;
        const parts = this.value.split('-');
        const yy = parts[0];
        const mm = parts[1];
        window.location.href = '?y=' + yy + '&m=' + mm;
      });
    });
    </script>

  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer_user.php'; ?>
