<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/academic.php';

$currentUser = current_user();
$userId = (int) ($currentUser['id'] ?? 0);
$role = (string) ($currentUser['role'] ?? 'student');
$departmentId = (int) ($currentUser['department_id'] ?? 0);
$activeTerm = sync_current_academic_term($pdo);
$academicSession = $activeTerm['academic_session'];
$semester = $activeTerm['semester'];
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$today = date('l');
$todayLabel = date('l, j F Y');
$stats = [];
$quickActions = [];
$classes = [];
$courseRows = [];
$welcomeRole = 'STUDENT WORKSPACE';
$welcomeMessage = 'Here is your academic schedule at a glance.';

function sync_dashboard_time(string $time): string
{
    return date('g:i A', strtotime($time));
}

if ($role === 'student') {
    $welcomeRole = 'STUDENT WORKSPACE';
    $courseCountQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM student_courses
         WHERE student_id = ? AND academic_session = ? AND semester = ?'
    );
    $courseCountQuery->execute([$userId, $academicSession, $semester]);
    $courseCount = (int) $courseCountQuery->fetchColumn();

    $scheduleQuery = $pdo->prepare(
        "SELECT c.course_code, c.title AS course_title,
                COALESCE(l.full_name, 'Not assigned') AS lecturer_name,
                s.day_of_week, s.start_time, s.end_time, s.venue, s.class_section
         FROM student_courses sc
         INNER JOIN class_schedules s
           ON s.course_id = sc.course_id
          AND s.academic_session = sc.academic_session
          AND s.semester = sc.semester
         INNER JOIN courses c ON c.id = s.course_id
         LEFT JOIN users l ON l.id = s.lecturer_id
         WHERE sc.student_id = ?
           AND sc.academic_session = ?
           AND sc.semester = ?
           AND s.status = 'scheduled'
         ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
                  s.start_time"
    );
    $scheduleQuery->execute([$userId, $academicSession, $semester]);
    $allClasses = $scheduleQuery->fetchAll();
    $classes = array_values(array_filter($allClasses, static function (array $class) use ($today): bool {
        return $class['day_of_week'] === $today;
    }));
    $courseQuery = $pdo->prepare(
        'SELECT c.course_code, c.title
         FROM student_courses sc
         INNER JOIN courses c ON c.id = sc.course_id
         WHERE sc.student_id = ? AND sc.academic_session = ? AND sc.semester = ?
         ORDER BY c.course_code LIMIT 8'
    );
    $courseQuery->execute([$userId, $academicSession, $semester]);
    $courseRows = $courseQuery->fetchAll();

    $nextClass = null;
    foreach ($classes as $class) {
        if ($class['end_time'] > date('H:i:s')) {
            $nextClass = $class;
            break;
        }
    }
    $stats = [
        ['Registered courses', (string) $courseCount, 'Your current course list', 'green-icon', '▣'],
        ['Classes this week', (string) count($allClasses), 'Across your registered courses', 'blue-icon', '▤'],
        ['Today’s classes', (string) count($classes), $classes ? 'Scheduled for today' : 'No classes scheduled today', 'purple-icon', '◷'],
        ['Next class', $nextClass ? sync_dashboard_time($nextClass['start_time']) : 'None', $nextClass ? $nextClass['course_code'] : 'No upcoming class today', 'amber-icon', '✓'],
    ];
    $quickActions = [
        ['courses.php', 'Manage courses', 'Add, remove or restore your courses', '▣', 'green-icon'],
        ['timetable.php', 'View weekly timetable', 'See your classes across seven days', '▤', 'blue-icon'],
    ];
} elseif ($role === 'lecturer') {
    $welcomeRole = 'LECTURER WORKSPACE';
    $requestQuery = $pdo->prepare("SELECT COUNT(*) FROM lecturer_course_requests WHERE lecturer_id = ? AND status = 'pending'");
    $requestQuery->execute([$userId]);
    $pendingRequests = (int) $requestQuery->fetchColumn();

    $approvedQuery = $pdo->prepare("SELECT COUNT(*) FROM lecturer_course_requests WHERE lecturer_id = ? AND status = 'approved'");
    $approvedQuery->execute([$userId]);
    $approvedCourses = (int) $approvedQuery->fetchColumn();

    $classQuery = $pdo->prepare(
        "SELECT c.course_code, c.title AS course_title, s.day_of_week, s.start_time, s.end_time,
                s.venue, s.class_section
         FROM class_schedules s
         INNER JOIN courses c ON c.id = s.course_id
         WHERE s.lecturer_id = ? AND s.academic_session = ? AND s.semester = ? AND s.status = 'scheduled'
         ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time"
    );
    $classQuery->execute([$userId, $academicSession, $semester]);
    $allClasses = $classQuery->fetchAll();
    $classes = array_values(array_filter($allClasses, static fn(array $class): bool => $class['day_of_week'] === $today));
    $stats = [
        ['Pending requests', (string) $pendingRequests, 'Courses awaiting admin review', 'amber-icon', '◷'],
        ['Approved courses', (string) $approvedCourses, 'Courses you can schedule', 'green-icon', '✓'],
        ['Classes scheduled', (string) count($allClasses), 'Your saved classes this semester', 'blue-icon', '▤'],
        ['Today’s classes', (string) count($classes), 'Classes assigned for today', 'purple-icon', '▣'],
    ];
    $quickActions = [
        ['lecturer_courses.php', 'Teaching requests', 'Request or review your course access', '✓', 'green-icon'],
        ['schedule_class.php', 'Schedule a class', 'Set a day, time, venue and section', '＋', 'blue-icon'],
    ];
} elseif ($role === 'course_rep') {
    $welcomeRole = 'COURSE REPRESENTATIVE WORKSPACE';
    $availableQuery = $pdo->prepare(
        "SELECT COUNT(*) FROM courses c
         WHERE c.department_id = ? AND c.is_active = 1
           AND NOT EXISTS (
             SELECT 1 FROM class_schedules s
             WHERE s.course_id = c.id AND s.academic_session = ?
               AND s.semester = ? AND s.status = 'scheduled'
           )"
    );
    $availableQuery->execute([$departmentId, $academicSession, $semester]);
    $unscheduledCourses = (int) $availableQuery->fetchColumn();

    $classQuery = $pdo->prepare(
        "SELECT c.course_code, c.title AS course_title, s.day_of_week, s.start_time, s.end_time,
                s.venue, s.class_section
         FROM class_schedules s
         INNER JOIN courses c ON c.id = s.course_id
         WHERE s.scheduled_by = ? AND s.lecturer_id IS NULL
           AND s.academic_session = ? AND s.semester = ? AND s.status = 'scheduled'
         ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time"
    );
    $classQuery->execute([$userId, $academicSession, $semester]);
    $allClasses = $classQuery->fetchAll();
    $classes = array_values(array_filter($allClasses, static fn(array $class): bool => $class['day_of_week'] === $today));
    $stats = [
        ['Unscheduled courses', (string) $unscheduledCourses, 'Active courses in your department', 'amber-icon', '◷'],
        ['Classes you scheduled', (string) count($allClasses), 'Fallback classes saved', 'green-icon', '✓'],
        ['Today’s classes', (string) count($classes), 'Your fallback classes today', 'blue-icon', '▤'],
        ['Scheduling position', 'Assigned', 'Department representative access', 'purple-icon', '♙'],
    ];
    $quickActions = [
        ['rep_schedule.php', 'Schedule an unscheduled course', 'Add a class when no lecturer has scheduled it', '＋', 'green-icon'],
        ['timetable.php', 'View student timetable', 'Check the published weekly schedule', '▤', 'blue-icon'],
    ];
} elseif ($role === 'admin') {
    $welcomeRole = 'ADMINISTRATOR WORKSPACE';
    $usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $coursesCount = (int) $pdo->query('SELECT COUNT(*) FROM courses WHERE is_active = 1')->fetchColumn();
    $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM lecturer_course_requests WHERE status = 'pending'")->fetchColumn();
    $conflictsCount = (int) $pdo->query("SELECT COUNT(*) FROM timetable_conflicts WHERE status IN ('open','reviewing')")->fetchColumn();
    $classQuery = $pdo->prepare(
        "SELECT c.course_code, c.title AS course_title, s.day_of_week, s.start_time, s.end_time,
                s.venue, s.class_section
         FROM class_schedules s
         INNER JOIN courses c ON c.id = s.course_id
         WHERE s.academic_session = ? AND s.semester = ? AND s.status = 'scheduled'
         ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time
         LIMIT 10"
    );
    $classQuery->execute([$academicSession, $semester]);
    $allClasses = $classQuery->fetchAll();
    $classes = array_values(array_filter($allClasses, static fn(array $class): bool => $class['day_of_week'] === $today));
    $stats = [
        ['Active users', (string) $usersCount, 'Registered system accounts', 'green-icon', '♙'],
        ['Active courses', (string) $coursesCount, 'Courses available in the system', 'blue-icon', '▣'],
        ['Pending approvals', (string) $pendingCount, 'Lecturer requests to review', 'amber-icon', '◷'],
        ['Open conflicts', (string) $conflictsCount, 'Timetable cases needing attention', 'purple-icon', '⚠'],
    ];
    $quickActions = [
        ['admin_approvals.php', 'Lecturer approvals', 'Review pending course requests', '⚑', 'green-icon'],
        ['conflicts.php', 'Timetable conflicts', 'Review clashes and record decisions', '⚠', 'amber-icon'],
        ['admin_timetable.php', 'Generate timetable', 'Configure slots and generate classes', '▤', 'blue-icon'],
    ];
} else {
    http_response_code(403);
    exit('Your account role is not supported.');
}

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div>
    <p class="eyebrow"><?= e($welcomeRole) ?></p>
    <h1>Good day, <?= e($currentUser['full_name'] ?? 'User') ?> <span>✦</span></h1>
    <p class="muted"><?= e($welcomeMessage) ?></p>
  </div>
  <div class="date-chip"><span class="calendar-icon">▦</span><span><?= e($todayLabel) ?></span></div>
</div>

<section class="stats-grid" aria-label="Dashboard summary">
  <?php foreach ($stats as $stat): ?>
    <article class="stat-card">
      <div class="stat-top"><span><?= e($stat[0]) ?></span><span class="stat-icon <?= e($stat[3]) ?>"><?= e($stat[4]) ?></span></div>
      <div class="stat-number"><?= e($stat[1]) ?></div>
      <div class="stat-foot"><?= e($stat[2]) ?></div>
    </article>
  <?php endforeach; ?>
</section>

<section class="content-grid">
  <article class="panel timetable-panel" id="timetable">
    <div class="panel-heading">
      <div><h2>Today’s classes</h2><p class="muted">Your saved classes for <?= e($today) ?>.</p></div>
      <a class="text-link" href="timetable.php">View full timetable <span>↗</span></a>
    </div>
    <?php if (!$classes): ?>
      <div class="empty-state">There are no scheduled classes to show for today.</div>
    <?php else: ?>
      <div class="class-list">
        <?php foreach ($classes as $class): ?>
          <div class="class-row">
            <div class="class-time"><?= e(sync_dashboard_time($class['start_time'])) ?> - <?= e(sync_dashboard_time($class['end_time'])) ?></div>
            <div class="class-line"><span></span></div>
            <div class="class-details">
              <div class="class-title-row">
                <div><span class="course-code"><?= e($class['course_code']) ?></span><h3><?= e($class['course_title']) ?></h3></div>
                <span class="class-tag"><?= e($class['day_of_week']) ?></span>
              </div>
              <div class="class-meta"><span>⌂ <?= e($class['venue']) ?></span><span>Section: <?= e($class['class_section']) ?></span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <aside class="right-column">
    <article class="panel quick-panel">
      <div class="panel-heading"><div><h2>Quick actions</h2><p class="muted">Go directly to your workspace.</p></div></div>
      <?php foreach ($quickActions as $action): ?>
        <a class="quick-action" href="<?= e($action[0]) ?>">
          <span class="quick-icon <?= e($action[4]) ?>"><?= e($action[3]) ?></span>
          <span><strong><?= e($action[1]) ?></strong><small><?= e($action[2]) ?></small></span>
          <b>›</b>
        </a>
      <?php endforeach; ?>
    </article>
    <article class="tip-card">
      <div class="tip-icon">✦</div>
      <div><strong>Keep your information updated</strong><p>Check your course selections and timetable details regularly. Sync checks scheduling conflicts before classes are saved.</p></div>
    </article>
  </aside>
</section>

<?php if ($role === 'student' && $courseRows): ?>
<section class="panel course-panel" id="courses">
  <div class="panel-heading"><div><h2>Your registered courses</h2><p class="muted">Courses saved for <?= e($academicSession) ?>, Semester <?= (int) $semester ?>.</p></div><a class="text-link" href="courses.php">Manage courses <span>↗</span></a></div>
  <div class="course-chips">
    <?php foreach ($courseRows as $course): ?>
      <span><b><?= e($course['course_code']) ?></b> <?= e($course['title']) ?></span>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
