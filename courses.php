<?php
$pageTitle = 'My courses';
$activePage = 'courses';
$courseList = [
 ['CSC 401','Software Engineering','4 Units','Core','Registered'],
 ['CSC 405','Database Systems','3 Units','Core','Registered'],
 ['CSC 409','Computer Networks','3 Units','Core','Registered'],
 ['CSC 411','Artificial Intelligence','3 Units','Elective','Available'],
 ['CSC 415','Research Methods','2 Units','Core','Registered'],
 ['CSC 417','Project Management','2 Units','Elective','Available'],
];
include 'includes/header.php';
?>
<div class="welcome-row"><div><p class="eyebrow">ACADEMIC RECORD</p><h1>My courses</h1><p class="muted">Review your department courses and manage your selections.</p></div><div class="date-chip"><span class="calendar-icon">▣</span><span>400 Level · Computer Science</span></div></div>
<div class="stats-grid course-stats">
  <article class="stat-card"><div class="stat-top"><span>Registered courses</span><span class="stat-icon green-icon">▣</span></div><div class="stat-number">04</div><div class="stat-foot">Courses in your current selection</div></article>
  <article class="stat-card"><div class="stat-top"><span>Registered units</span><span class="stat-icon blue-icon">Σ</span></div><div class="stat-number">12</div><div class="stat-foot">Total course credit units</div></article>
  <article class="stat-card"><div class="stat-top"><span>Available courses</span><span class="stat-icon purple-icon">＋</span></div><div class="stat-number">02</div><div class="stat-foot">Courses you can add in this demo</div></article>
  <article class="stat-card"><div class="stat-top"><span>Department</span><span class="stat-icon amber-icon">⌂</span></div><div class="stat-number department-number">CSC</div><div class="stat-foot">Computer Science</div></article>
</div>
<section class="panel course-management-panel" id="course-list">
  <div class="panel-heading"><div><h2>Course registration</h2><p class="muted">Department and level courses appear as defaults. You can remove a default course and add it back later.</p></div><span class="demo-pill">DEMO DATA</span></div>
  <div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Credit units</th><th>Type</th><th>Status</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($courseList as $course): ?><tr data-course-row><td><div class="table-course-code"><?= htmlspecialchars($course[0]) ?></div><strong><?= htmlspecialchars($course[1]) ?></strong></td><td><?= htmlspecialchars($course[2]) ?></td><td><span class="type-pill"><?= htmlspecialchars($course[3]) ?></span></td><td><span class="status-pill <?= $course[4] === 'Registered' ? 'status-registered' : 'status-available' ?>" data-status><?= htmlspecialchars($course[4]) ?></span></td><td><button type="button" class="small-action <?= $course[4] === 'Registered' ? 'remove-course' : 'add-course' ?>" data-toggle-course><?= $course[4] === 'Registered' ? 'Remove' : 'Add course' ?></button></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="panel-bottom"><span class="muted">Changes here are temporary and reset when you reload the page.</span><button type="button" class="primary-button compact-button" id="saveCourses">Save course selection <span>→</span></button></div>
  <p class="form-feedback" id="courseMessage" hidden>Your demo selection has changed. Database saving will be added with the registration backend.</p>
</section>
<section class="tip-card course-tip"><div class="tip-icon">✦</div><div><strong>How course allocation works</strong><p>Sync will use your department and academic level to suggest default courses. You remain in control of your selection and can restore a removed default course.</p></div></section>
<?php include 'includes/footer.php'; ?>
