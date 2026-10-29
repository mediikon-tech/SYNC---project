<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$currentUser = current_user();
$pageTitle = 'Weekly timetable';
$activePage = 'timetable';
$academicSession = '2026/2027';
$semester = 1;
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$slots = [
    ['label' => '08:00 - 10:00', 'start' => 8],
    ['label' => '10:00 - 12:00', 'start' => 10],
    ['label' => '12:00 - 14:00', 'start' => 12],
    ['label' => '14:00 - 16:00', 'start' => 14],
];
$classes = [];
$allClasses = [];
$venues = [];
$teachingDays = [];
$registeredCourseCount = 0;
$error = '';

foreach ($days as $day) {
    $classes[$day] = [[], [], [], []];
}

require_once __DIR__ . '/config/database.php';

if (($currentUser['role'] ?? '') === 'student') {
    $countQuery = $pdo->prepare(
        'SELECT COUNT(DISTINCT course_id) FROM student_courses
         WHERE student_id = ? AND academic_session = ? AND semester = ?'
    );
    $countQuery->execute([(int) $currentUser['id'], $academicSession, $semester]);
    $registeredCourseCount = (int) $countQuery->fetchColumn();

    $scheduleQuery = $pdo->prepare(
        'SELECT s.id, c.course_code, c.title AS course_title,
                lecturer.full_name AS lecturer_name,
                s.day_of_week, s.start_time, s.end_time, s.venue,
                s.class_section, s.status
         FROM student_courses sc
         JOIN class_schedules s
           ON s.course_id = sc.course_id
          AND s.academic_session = sc.academic_session
          AND s.semester = sc.semester
         JOIN courses c ON c.id = s.course_id
         JOIN users lecturer ON lecturer.id = s.lecturer_id
         WHERE sc.student_id = :student_id
           AND sc.academic_session = :academic_session
           AND sc.semester = :semester
           AND s.status = \'scheduled\'
         ORDER BY FIELD(s.day_of_week, \'Monday\', \'Tuesday\', \'Wednesday\', \'Thursday\', \'Friday\', \'Saturday\', \'Sunday\'),
                  s.start_time, c.course_code'
    );
    $scheduleQuery->execute([
        'student_id' => (int) $currentUser['id'],
        'academic_session' => $academicSession,
        'semester' => $semester,
    ]);
    $allClasses = $scheduleQuery->fetchAll();

    foreach ($allClasses as $class) {
        $day = (string) $class['day_of_week'];
        $venues[(string) $class['venue']] = true;
        $teachingDays[$day] = true;
        if (isset($classes[$day])) {
            $startHour = (int) substr((string) $class['start_time'], 0, 2);
            $slotIndex = (int) floor(($startHour - 8) / 2);
            if ($slotIndex >= 0 && $slotIndex < count($slots)) {
                $classes[$day][$slotIndex][] = $class;
            }
        }
    }
} else {
    $error = 'This timetable view is currently configured for student accounts.';
}

function sync_time_label(string $time): string
{
    return date('g:i A', strtotime($time));
}

include __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div><p class="eyebrow">PLAN YOUR WEEK</p><h1>Weekly timetable</h1><p class="muted">Your saved classes for registered courses, with lecturer, time, venue and class section.</p></div>
  <form method="get" action="timetable.php"><button class="primary-button timetable-action" type="submit">Refresh timetable <span>↻</span></button></form>
</div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>

<div class="timetable-summary">
  <div class="summary-item"><span class="summary-icon green-icon">▤</span><div><small>Scheduled classes</small><strong><?= count($allClasses) ?></strong></div></div>
  <div class="summary-item"><span class="summary-icon blue-icon">◷</span><div><small>Teaching days</small><strong><?= count($teachingDays) ?> days</strong></div></div>
  <div class="summary-item"><span class="summary-icon purple-icon">⌂</span><div><small>Venues this week</small><strong><?= count($venues) ?> venues</strong></div></div>
  <div class="summary-item"><span class="summary-icon amber-icon">✓</span><div><small>Registered courses</small><strong><?= $registeredCourseCount ?></strong></div></div>
</div>

<section class="panel weekly-panel" id="weekly-view">
  <div class="panel-heading"><div><h2>Class schedule</h2><p class="muted">2026/2027 Session · Semester 1</p></div><span class="demo-pill">SAVED SCHEDULES</span></div>
  <div class="timetable-legend"><span><i class="legend-dot"></i> Scheduled class</span><span><i class="legend-dot legend-empty"></i> Free period</span></div>
  <?php if ($allClasses === []): ?>
    <div class="empty-state course-empty">No scheduled classes were found for your saved Semester 1 courses. Once a lecturer or course representative schedules an approved course, it will appear here.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="week-table"><thead><tr><th>Time</th><?php foreach ($days as $day): ?><th><?= e($day) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($slots as $slotIndex => $slot): ?><tr><th class="time-cell"><?= e($slot['label']) ?></th><?php foreach ($days as $day): ?><td>
        <?php if ($classes[$day][$slotIndex] !== []): ?>
          <?php foreach ($classes[$day][$slotIndex] as $class): ?>
            <div class="week-class"><span><?= e($class['course_code']) ?></span><strong><?= e($class['course_title']) ?></strong><small>◷ <?= e(sync_time_label((string) $class['start_time'])) ?>–<?= e(sync_time_label((string) $class['end_time'])) ?></small><small>⌂ <?= e($class['venue']) ?></small><small>♙ <?= e($class['lecturer_name']) ?></small><small>Section: <?= e($class['class_section']) ?></small></div>
          <?php endforeach; ?>
        <?php else: ?><div class="free-cell">Free</div><?php endif; ?>
      </td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <div class="timetable-note" id="timetableMessage">This page reads saved timetable records from MySQL. Server-side clash validation and schedule creation by lecturers/course representatives are the next scheduling features to implement.</div>
</section>

<section class="panel class-list-panel">
  <div class="panel-heading"><div><h2>All scheduled classes</h2><p class="muted">Every scheduled class linked to your selected courses.</p></div></div>
  <?php if ($allClasses === []): ?>
    <div class="empty-state course-empty">Your class list will appear here when matching schedules have been saved.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Day</th><th>Time</th><th>Venue</th><th>Lecturer</th><th>Section</th></tr></thead><tbody>
      <?php foreach ($allClasses as $class): ?><tr><td><div class="table-course-code"><?= e($class['course_code']) ?></div><strong><?= e($class['course_title']) ?></strong></td><td><?= e($class['day_of_week']) ?></td><td><?= e(sync_time_label((string) $class['start_time'])) ?> – <?= e(sync_time_label((string) $class['end_time'])) ?></td><td><?= e($class['venue']) ?></td><td><?= e($class['lecturer_name']) ?></td><td><?= e($class['class_section']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
