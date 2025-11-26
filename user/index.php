<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/header_user.php';

/*
  Fetch top 3 active courses. Columns exist:
  id, name, category, description, duration_hours, duration_unit, price, gst_included, status, picture_url
*/
$courses = qall("
  SELECT 
    id,
    name,
    category,
    description,
    duration_hours,
    duration_unit,
    price,
    gst_included,
    status,
    picture_url
  FROM courses
  WHERE status = 'active'
  ORDER BY id DESC
  LIMIT 3
");

/**
 * Normalize any stored picture_url into a public, absolute URL.
 * Canonical folder: /admin/uploads/courses/<filename>
 * Accept legacy variants and fix them. If invalid, fallback to placeholder.
 */
function normalize_course_image(?string $raw): string {
  $fallback = '/userassets/course-fallback.jpg';
  $path = trim((string)$raw);

  if ($path === '') return $fallback;

  // External absolute URL? just return.
  if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) return $path;

  // Ensure it starts with a slash for consistent replaces
  $path = '/' . ltrim($path, '/');

  // Unify common legacy prefixes to the canonical folder
  $path = str_replace('/admin/upload/courses', '/admin/uploads/courses', $path);
  $path = str_replace('/upload/courses', '/admin/uploads/courses', $path);
  $path = str_replace('/uploads/courses', '/admin/uploads/courses', $path);

  // If still not under the canonical prefix, rebuild from just the filename
  $canonicalPrefix = '/admin/uploads/courses/';
  if (strpos($path, $canonicalPrefix) !== 0) {
    $filename = basename($path);
    if ($filename === '' || $filename === '/' || $filename === '.' || $filename === '..') {
      return $fallback;
    }
    $path = $canonicalPrefix . $filename;
  }

  return $path;
}
?>

<!-- ✅ Hero section now uses admin/uploads/home.jpg -->
<section class="hero-section" style="
  background-image: url('/admin/uploads/home.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
">
  <div class="hero-overlay">
    <div class="hero-content">
      <h1 class="hero-title">Industry training made simple</h1>
      <p class="hero-desc">
        Enroll for yourself or for employees across campuses.
      </p>
      <a class="hero-cta" href="/user/booking_type.php">Book now</a>
    </div>
  </div>
</section>

<!-- ===== Product-style Courses (3 cards / row on desktop) ===== -->
<section class="courses-section"
  style="
    max-width: var(--max-width);
    margin: 32px auto;
    padding: 0 var(--spacing);
  ">

  <style>
    .courses-grid{display:grid;grid-template-columns:1fr;gap:16px}
    @media(min-width:600px){.courses-grid{grid-template-columns:repeat(2,1fr)}}
    @media(min-width:1024px){.courses-grid{grid-template-columns:repeat(3,1fr)}}
    .course-card{position:relative;background:#fff;border:1px solid var(--border-soft);
      border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-card);cursor:pointer}
    .course-thumb{aspect-ratio:16/9;overflow:hidden;background:#f3f4f6;
      background-size:cover;background-position:center;background-repeat:no-repeat}
    .course-body{padding:16px;position:relative;z-index:2}
    .course-name{margin:0 0 6px;font-size:1.05rem;font-weight:600}
    .course-desc{margin:0 0 10px;color:var(--text-dim);font-size:.95rem;line-height:1.45}
    .course-meta{list-style:none;padding:0;margin:0 0 12px;display:grid;gap:6px;
      font-size:.9rem;color:var(--text-main)}
    .stretched-link{position:absolute;inset:0;z-index:1;text-indent:-9999px;overflow:hidden}
  </style>

  <div class="courses-head" style="text-align:center; margin-bottom:16px;">
    <h2 class="courses-title" style="margin:0 0 6px; font-size:1.3rem; font-weight:600;">Top Courses</h2>
    <p class="courses-subtitle" style="margin:0; color:var(--text-dim);">Pick a course and book in minutes.</p>
  </div>

  <div class="courses-grid">
    <?php if (!empty($courses)): ?>
      <?php foreach ($courses as $c):
        $img  = normalize_course_image($c['picture_url'] ?? null);
        $name = $c['name'] ?? 'Course';

        $rawDesc = trim((string)($c['description'] ?? ''));
        $short   = mb_substr($rawDesc !== '' ? $rawDesc : (string)$name, 0, 180);

        $durText = '';
        $hours = isset($c['duration_hours']) ? (int)$c['duration_hours'] : null;
        $unit  = strtolower((string)($c['duration_unit'] ?? ''));
        if ($hours && $unit) {
          if ($unit === 'days' || $unit === 'day') {
            $durText = $hours . ' day' . ($hours > 1 ? 's' : '');
          } else {
            $durText = $hours . ' hour' . ($hours > 1 ? 's' : '');
          }
        }

        $price = isset($c['price']) ? (float)$c['price'] : null;
        $gstIncluded = isset($c['gst_included']) ? (int)$c['gst_included'] : 1;
        if ($price !== null) {
          $price_str = 'NZ$' . number_format($price, 2) . ($gstIncluded ? ' (incl. GST)' : ' + GST');
        } else {
          $price_str = 'Contact for pricing';
        }

        $course_link = '/user/course_selection.php';
        $qs = ['from'=>'home'];
        if (!empty($c['id'])) { $qs['course_id'] = (int)$c['id']; }
        if (!empty($name))    { $qs['course_name'] = $name; }
        $course_link .= '?' . http_build_query($qs);
      ?>
      <article class="course-card" onclick="window.location.href='<?= htmlspecialchars($course_link) ?>'">
        <a class="stretched-link" href="<?= htmlspecialchars($course_link) ?>">Go</a>

        <div class="course-thumb" style="background-image:url('<?= htmlspecialchars($img) ?>');"></div>

        <div class="course-body">
          <h3 class="course-name"><?= htmlspecialchars($name) ?></h3>
          <?php if (!empty($short)): ?>
            <p class="course-desc"><?= nl2br(htmlspecialchars($short)) ?></p>
          <?php endif; ?>

          <ul class="course-meta">
            <?php if ($durText !== ''): ?>
              <li><strong>Duration:</strong> <?= htmlspecialchars($durText) ?></li>
            <?php endif; ?>
            <li><strong>Cost:</strong> <?= htmlspecialchars($price_str) ?></li>
          </ul>

          <a href="<?= htmlspecialchars($course_link) ?>" class="btn-primary" aria-label="Book <?= htmlspecialchars($name) ?>">Book Now</a>
        </div>
      </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="courses-empty" style="text-align:center;color:var(--text-dim);">
        Courses will appear here soon. Please check back later.
      </p>
    <?php endif; ?>
  </div>
</section>

<section class="cta-block">
  <h2 class="cta-headline">Ready to book your training?</h2>
  <a class="btn-primary" href="/user/booking_type.php" aria-label="Start booking process">
    Book now
  </a>
</section>

<?php
require_once __DIR__ . '/footer_user.php';
