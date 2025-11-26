<?php
require_once __DIR__ . '/../includes/auth_student.php';
require_once __DIR__ . '/../includes/db.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);

$myCourses = qall("
    SELECT 
      e.id AS enrollment_id,
      c.name  AS course_name,
      cp.name AS campus_name,
      e.training_date,
      e.status
    FROM enrollments e
    JOIN offerings o ON o.id = e.offering_id
    JOIN courses  c ON c.id = o.course_id
    JOIN campuses cp ON cp.id = o.campus_id
    WHERE e.user_id = ?
    ORDER BY e.training_date DESC
", [$user_id]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Courses — VisionDrive</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/userassets/style_user.css">
</head>
<body>
<?php include __DIR__ . '/header_user.php'; ?>
<main class="container" style="max-width:900px;margin:40px auto;">
  <h1>My Courses</h1>
  <?php if (empty($myCourses)): ?>
      <p>You have not enrolled in any course yet.</p>
  <?php else: ?>
  <table class="table" border="1" cellpadding="8" cellspacing="0" width="100%">
    <thead>
      <tr>
        <th>Course</th>
        <th>Campus</th>
        <th>Date</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($myCourses as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['course_name']) ?></td>
        <td><?= htmlspecialchars($c['campus_name']) ?></td>
        <td><?= htmlspecialchars($c['training_date']) ?></td>
        <td><?= htmlspecialchars(ucfirst($c['status'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/footer_user.php'; ?>
</body>
</html>
