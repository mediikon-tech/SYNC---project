<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$currentUser = current_user();
$role = (string) ($currentUser['role'] ?? 'student');
$userId = (int) ($currentUser['id'] ?? 0);
$departmentId = (int) ($currentUser['department_id'] ?? 0);
$pageTitle = 'Weekly timetable';
$activePage = 'timetable';
$academicSession = '2026/2027';
$semester = 1;
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$slots = [];
for ($hour = 0; $hour < 24; $hour++) {
    $slots[] = [
        'start' => $hour * 60,
        'end' => ($hour + 1) * 60,
        'label' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
    ];
}
$classes = [];
$allClasses = [];
$venues = [];
$teachingDays = [];
$courseCount = 0;
$error = '';

foreach ($days as $day) {
    $classes[$day] = array_fill(0, count($slots), []);
}

require_once __DIR__ . '/config/database.php';

$baseSelect = "SELECT s.id, c.course_code, c.title AS course_title,
                      COALESCE(lecturer.full_name, 'Not assigned') AS lecturer_name,
                      s.day_of_week, s.start_time, s.end_time, s.venue,
                      s.class_section, s.status
               FROM class_schedules s
               INNER JOIN courses c ON c.id = s.course_id
               LEFT JOIN users lecturer ON lecturer.id = s.lecturer_id";

if ($role === 'student') {
    $countQuery = $pdo->prepare(
        'SELECT COUNT(DISTINCT course_id) FROM student_courses
         WHERE student_id = ? AND academic_session = ? AND semester = ?'
    );
    $countQuery->execute([$userId, $academicSession, $semester]);
    $courseCount = (int) $countQuery->fetchColumn();

    $scheduleQuery = $pdo->prepare(
        "SELECT s.id, c.course_code, c.title AS course_title,
                COALESCE(lecturer.full_name, 'Not assigned') AS lecturer_name,
                s.day_of_week, s.start_time, s.end_time, s.venue,
                s.class_section, s.status
         FROM student_courses sc
         INNER JOIN class_schedules s
           ON s.course_id = sc.course_id
          AND s.academic_session = sc.academic_session
          AND s.semester = sc.semester
         INNER JOIN courses c ON c.id = s.course_id
         LEFT JOIN users lecturer ON lecturer.id = s.lecturer_id
         WHERE sc.student_id = ? AND sc.academic_session = ?
           AND sc.semester = ? AND s.status = 'scheduled'
         ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
                  s.start_time, c.course_code"
    );
    $scheduleQuery->execute([$userId, $academicSession, $semester]);
    $allClasses = $scheduleQuery->fetchAll();
} elseif ($role === 'lecturer') {
    $scheduleQuery = $pdo->prepare(
        $baseSelect . " WHERE s.lecturer_id = ? AND s.academic_session = ?
                        AND s.semester = ? AND s.status = 'scheduled'
                        ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time"
    );
    $scheduleQuery->execute([$userId, $academicSession, $semester]);
    $allClasses = $scheduleQuery->fetchAll();
    $countQuery = $pdo->prepare(
        "SELECT COUNT(DISTINCT course_id) FROM lecturer_course_requests
         WHERE lecturer_id = ? AND status = 'approved'"
    );
    $countQuery->execute([$userId]);
    $courseCount = (int) $countQuery->fetchColumn();
} elseif ($role === 'course_rep') {
    $scheduleQuery = $pdo->prepare(
        $baseSelect . " WHERE c.department_id = ? AND s.academic_session = ?
                        AND s.semester = ? AND s.status = 'scheduled'
                        ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time"
    );
    $scheduleQuery->execute([$departmentId, $academicSession, $semester]);
    $allClasses = $scheduleQuery->fetchAll();
    $countQuery = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE department_id = ? AND is_active = 1');
    $countQuery->execute([$departmentId]);
    $courseCount = (int) $countQuery->fetchColumn();
} elseif ($role === 'admin') {
    $scheduleQuery = $pdo->prepare(
        $baseSelect . " WHERE s.academic_session = ? AND s.semester = ? AND s.status = 'scheduled'
                        ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time"
    );
    $scheduleQuery->execute([$academicSession, $semester]);
    $allClasses = $scheduleQuery->fetchAll();
    $courseCount = (int) $pdo->query('SELECT COUNT(*) FROM courses WHERE is_active = 1')->fetchColumn();
} else {
    $error = 'Your account role cannot access this timetable.';
}

foreach ($allClasses as $class) {
    $day = (string) $class['day_of_week'];
    $venues[(string) $class['venue']] = true;
    $teachingDays[$day] = true;
    if (isset($classes[$day])) {
        $startParts = explode(':', (string) $class['start_time']);
        $endParts = explode(':', (string) $class['end_time']);
        $startMinute = ((int) $startParts[0] * 60) + (int) $startParts[1];
        $endMinute = ((int) $endParts[0] * 60) + (int) $endParts[1];

        foreach ($slots as $slotIndex => $slot) {
            if ($startMinute < $slot['end'] && $endMinute > $slot['start']) {
                $classes[$day][$slotIndex][] = [
                    'class' => $class,
                    'continues' => $startMinute < $slot['start'],
                ];
            }
        }
    }
}

function sync_time_label(string $time): string
{
    return date('g:i A', strtotime($time));
}

$courseCountLabel = $role === 'student' ? 'Registered courses' : ($role === 'lecturer' ? 'Approved courses' : 'Active courses');
$intro = match ($role) {
    'student' => 'Classes for your registered courses.',
    'lecturer' => 'Classes assigned to you as a lecturer.',
    'course_rep' => 'Scheduled classes across your department.',
    'admin' => 'All saved classes for the current semester.',
    default => 'Weekly class schedule.',
};

include __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div><p class="eyebrow">PLAN YOUR WEEK</p><h1>Weekly timetable</h1><p class="muted"><?= e($intro) ?> Monday through Sunday, with lecturer, time, venue and class section.</p></div>
  <form method="get" action="timetable.php"><button class="primary-button timetable-action" type="submit">Refresh timetable <span>↻</span></button></form>
</div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>

<div class="timetable-summary">
  <div class="summary-item"><span class="summary-icon green-icon">▤</span><div><small>Scheduled classes</small><strong><?= count($allClasses) ?></strong></div></div>
  <div class="summary-item"><span class="summary-icon blue-icon">◷</span><div><small>Teaching days</small><strong><?= count($teachingDays) ?> days</strong></div></div>
  <div class="summary-item"><span class="summary-icon purple-icon">⌂</span><div><small>Venues this week</small><strong><?= count($venues) ?> venues</strong></div></div>
  <div class="summary-item"><span class="summary-icon amber-icon">✓</span><div><small><?= e($courseCountLabel) ?></small><strong><?= $courseCount ?></strong></div></div>
</div>

<section class="panel weekly-panel" id="weekly-view">
  <div class="panel-heading"><div><h2>Class schedule</h2><p class="muted"><?= e($academicSession) ?> Session · Semester <?= (int) $semester ?></p></div><span class="demo-pill">SAVED SCHEDULES</span></div>
  <div class="timetable-legend"><span><i class="legend-dot"></i> Scheduled class</span><span><i class="legend-dot legend-empty"></i> Free period</span></div>
  <?php if ($allClasses === []): ?>
    <div class="empty-state course-empty">No scheduled classes were found for this view. Classes appear here after a lecturer or course representative saves a schedule for the current semester.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="week-table"><thead><tr><th>Time</th><?php foreach ($days as $day): ?><th><?= e($day) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($slots as $slotIndex => $slot): ?><tr><th class="time-cell"><?= e($slot['label']) ?></th><?php foreach ($days as $day): ?><td>
        <?php if ($classes[$day][$slotIndex] !== []): ?>
          <?php foreach ($classes[$day][$slotIndex] as $entry): $class = $entry['class']; ?>
            <?php if ($entry['continues']): ?>
              <div class="week-class week-class-continuation"><span><?= e($class['course_code']) ?></span><small>Continues until <?= e(sync_time_label((string) $class['end_time'])) ?></small></div>
            <?php else: ?>
              <div class="week-class"><span><?= e($class['course_code']) ?></span><strong><?= e($class['course_title']) ?></strong><small>◷ <?= e(sync_time_label((string) $class['start_time'])) ?>–<?= e(sync_time_label((string) $class['end_time'])) ?></small><small>⌂ <?= e($class['venue']) ?></small><small>♙ <?= e($class['lecturer_name']) ?></small><small>Section: <?= e($class['class_section']) ?></small></div>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?><div class="free-cell">Free</div><?php endif; ?>
      </td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <div class="timetable-note" id="timetableMessage">The weekly grid uses one-hour rows. Classes appear in every hour they overlap, with exact start and end times shown in the class details.</div>
</section>

<section class="panel class-list-panel">
  <div class="panel-heading"><div><h2>All scheduled classes</h2><p class="muted"><?= e($intro) ?></p></div></div>
  <?php if ($allClasses === []): ?>
    <div class="empty-state course-empty">The class list will appear here when matching schedules have been saved.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Day</th><th>Time</th><th>Venue</th><th>Lecturer</th><th>Section</th></tr></thead><tbody>
      <?php foreach ($allClasses as $class): ?><tr><td><div class="table-course-code"><?= e($class['course_code']) ?></div><strong><?= e($class['course_title']) ?></strong></td><td><?= e($class['day_of_week']) ?></td><td><?= e(sync_time_label((string) $class['start_time'])) ?> – <?= e(sync_time_label((string) $class['end_time'])) ?></td><td><?= e($class['venue']) ?></td><td><?= e($class['lecturer_name']) ?></td><td><?= e($class['class_section']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
