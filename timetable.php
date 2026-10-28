<?php
$pageTitle = 'Weekly timetable';
$activePage = 'timetable';
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$slots = ['08:00 - 10:00','10:00 - 12:00','12:00 - 02:00','02:00 - 04:00'];
$classes = [
 'Monday'=>[0=>['CSC 401','Software Engineering','LT 2','Dr. A. Musa'],2=>['CSC 405','Database Systems','Lab 1','Mr. J. Okafor']],
 'Tuesday'=>[1=>['CSC 409','Computer Networks','LT 1','Mrs. S. Bello']],
 'Wednesday'=>[0=>['CSC 411','Artificial Intelligence','LT 3','Dr. I. James'],3=>['CSC 415','Research Methods','Room B12','Prof. M. Ali']],
 'Thursday'=>[1=>['CSC 417','Project Management','LT 2','Mr. P. Eze']],
 'Friday'=>[2=>['CSC 405','Database Systems','Lab 1','Mr. J. Okafor']],
];
include 'includes/header.php';
?>
<div class="welcome-row"><div><p class="eyebrow">PLAN YOUR WEEK</p><h1>Weekly timetable</h1><p class="muted">See your scheduled classes, venues and lecturers in one view.</p></div><button class="primary-button timetable-action" id="generateTimetable" type="button">Generate timetable <span>↻</span></button></div>
<div class="timetable-summary">
  <div class="summary-item"><span class="summary-icon green-icon">▤</span><div><small>Scheduled classes</small><strong>07</strong></div></div>
  <div class="summary-item"><span class="summary-icon blue-icon">◷</span><div><small>Teaching days</small><strong>5 days</strong></div></div>
  <div class="summary-item"><span class="summary-icon purple-icon">⌂</span><div><small>Venues this week</small><strong>5 venues</strong></div></div>
  <div class="summary-item"><span class="summary-icon amber-icon">✓</span><div><small>Conflict check</small><strong class="status-number">Pending</strong></div></div>
</div>
<section class="panel weekly-panel" id="weekly-view">
  <div class="panel-heading"><div><h2>Class schedule</h2><p class="muted">Sample timetable for 400 Level Computer Science.</p></div><span class="demo-pill">SAMPLE WEEK</span></div>
  <div class="timetable-legend"><span><i class="legend-dot"></i> Scheduled class</span><span><i class="legend-dot legend-empty"></i> Free period</span></div>
  <div class="table-scroll"><table class="week-table"><thead><tr><th>Time</th><?php foreach ($days as $day): ?><th><?= htmlspecialchars($day) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($slots as $slotIndex => $slot): ?><tr><th class="time-cell"><?= htmlspecialchars($slot) ?></th><?php foreach ($days as $day): $class = $classes[$day][$slotIndex] ?? null; ?><td><?php if ($class): ?><div class="week-class"><span><?= htmlspecialchars($class[0]) ?></span><strong><?= htmlspecialchars($class[1]) ?></strong><small>⌂ <?= htmlspecialchars($class[2]) ?></small><small>♙ <?= htmlspecialchars($class[3]) ?></small></div><?php else: ?><div class="free-cell">Free</div><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="timetable-note" id="timetableMessage" role="status">This is sample timetable data. Generating and validating a real timetable will be connected to the database and conflict-checking rules.</div>
</section>
<section class="panel class-list-panel"><div class="panel-heading"><div><h2>All scheduled classes</h2><p class="muted">A simple list of classes in the current sample timetable.</p></div></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Day</th><th>Time</th><th>Venue</th><th>Lecturer</th></tr></thead><tbody>
<?php foreach ($days as $day): foreach ($classes[$day] as $slotIndex => $class): ?><tr><td><div class="table-course-code"><?= htmlspecialchars($class[0]) ?></div><strong><?= htmlspecialchars($class[1]) ?></strong></td><td><?= htmlspecialchars($day) ?></td><td><?= htmlspecialchars($slots[$slotIndex]) ?></td><td><?= htmlspecialchars($class[2]) ?></td><td><?= htmlspecialchars($class[3]) ?></td></tr><?php endforeach; endforeach; ?>
</tbody></table></div></section>
<?php include 'includes/footer.php'; ?>
