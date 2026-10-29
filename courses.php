<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$currentUser = current_user();
if (($currentUser['role'] ?? '') !== 'student') {
    http_response_code(403);
    exit('Course selection is available to student accounts only.');
}

$pageTitle = 'My courses';
$activePage = 'courses';
$academicSession = '2026/2027';
$error = '';
$courseList = [];
$registeredCount = 0;
$registeredUnits = 0;
$availableCount = 0;

$statusMessages = [
    'added' => 'Course added to your selection.',
    'removed' => 'Course removed. You can add it back at any time.',
];
$notice = $statusMessages[(string) ($_GET['status'] ?? '')] ?? '';

require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    $courseSemester = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT);

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Refresh the page and try again.';
    } elseif (!in_array($action, ['add', 'remove'], true) || !$courseId || !$courseSemester || $courseSemester < 1 || $courseSemester > 2) {
        $error = 'That course action is invalid. Please try again.';
    } else {
        // A student may only change courses mapped to their own department and level.
        $allowed = $pdo->prepare(
            'SELECT dcl.is_default
             FROM department_course_levels dcl
             JOIN courses c ON c.id = dcl.course_id AND c.department_id = dcl.department_id
             WHERE dcl.course_id = :course_id
               AND dcl.department_id = :department_id
               AND dcl.academic_level = :academic_level
               AND dcl.semester = :semester
               AND dcl.academic_session = :academic_session
               AND c.is_active = 1
             LIMIT 1'
        );
        $allowed->execute([
            'course_id' => $courseId,
            'department_id' => (int) ($currentUser['department_id'] ?? 0),
            'academic_level' => (int) ($currentUser['academic_level'] ?? 0),
            'semester' => $courseSemester,
            'academic_session' => $academicSession,
        ]);
        $mapping = $allowed->fetch();

        if (!$mapping) {
            $error = 'That course is not available for your department and level.';
        } else {
            try {
                if ($action === 'add') {
                    $save = $pdo->prepare(
                        "INSERT INTO student_courses (student_id, course_id, academic_session, semester, selection_source)
                         VALUES (:student_id, :course_id, :academic_session, :semester, :selection_source)
                         ON DUPLICATE KEY UPDATE selection_source = VALUES(selection_source)"
                    );
                    $save->execute([
                        'student_id' => (int) $currentUser['id'],
                        'course_id' => $courseId,
                        'academic_session' => $academicSession,
                        'semester' => $courseSemester,
                        'selection_source' => (int) $mapping['is_default'] === 1 ? 'default' : 'added',
                    ]);
                    header('Location: courses.php?status=added');
                    exit;
                }

                $remove = $pdo->prepare(
                    'DELETE FROM student_courses
                     WHERE student_id = :student_id AND course_id = :course_id
                       AND academic_session = :academic_session AND semester = :semester'
                );
                $remove->execute([
                    'student_id' => (int) $currentUser['id'],
                    'course_id' => $courseId,
                    'academic_session' => $academicSession,
                    'semester' => $courseSemester,
                ]);
                header('Location: courses.php?status=removed');
                exit;
            } catch (PDOException $exception) {
                error_log('Sync course selection failed: ' . $exception->getMessage());
                $error = 'Your course selection could not be saved. Please try again.';
            }
        }
    }
}

$courseQuery = $pdo->prepare(
    'SELECT c.id, c.course_code, c.title, c.credit_units, c.course_type,
            dcl.semester, dcl.is_default,
            CASE WHEN sc.id IS NULL THEN 0 ELSE 1 END AS is_registered
     FROM department_course_levels dcl
     JOIN courses c ON c.id = dcl.course_id AND c.department_id = dcl.department_id
     LEFT JOIN student_courses sc
       ON sc.student_id = :student_id
      AND sc.course_id = c.id
      AND sc.academic_session = dcl.academic_session
      AND sc.semester = dcl.semester
     WHERE dcl.department_id = :department_id
       AND dcl.academic_level = :academic_level
       AND dcl.academic_session = :session_filter
       AND c.is_active = 1
     ORDER BY dcl.semester, c.course_code'
);
$courseQuery->execute([
    'student_id' => (int) $currentUser['id'],
    'department_id' => (int) ($currentUser['department_id'] ?? 0),
    'academic_level' => (int) ($currentUser['academic_level'] ?? 0),
    'session_filter' => $academicSession,
]);
$courseList = $courseQuery->fetchAll();

foreach ($courseList as $course) {
    if ((int) $course['is_registered'] === 1) {
        $registeredCount++;
        $registeredUnits += (int) $course['credit_units'];
    } else {
        $availableCount++;
    }
}

$departmentName = (string) ($currentUser['department_name'] ?? 'Your department');
$levelLabel = (int) ($currentUser['academic_level'] ?? 0) . ' Level';
include __DIR__ . '/includes/header.php';
?>
<div class="welcome-row"><div><p class="eyebrow">ACADEMIC RECORD</p><h1>My courses</h1><p class="muted">Manage the courses allocated to your department and academic level.</p></div><div class="date-chip"><span class="calendar-icon">▣</span><span><?= e($levelLabel) ?> · <?= e($departmentName) ?></span></div></div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($notice !== ''): ?><p class="form-feedback course-success" role="status"><?= e($notice) ?></p><?php endif; ?>

<div class="stats-grid course-stats">
  <article class="stat-card"><div class="stat-top"><span>Registered courses</span><span class="stat-icon green-icon">▣</span></div><div class="stat-number"><?= $registeredCount ?></div><div class="stat-foot">Saved for the 2026/2027 session</div></article>
  <article class="stat-card"><div class="stat-top"><span>Registered units</span><span class="stat-icon blue-icon">Σ</span></div><div class="stat-number"><?= $registeredUnits ?></div><div class="stat-foot">Total units in your current selection</div></article>
  <article class="stat-card"><div class="stat-top"><span>Available courses</span><span class="stat-icon purple-icon">＋</span></div><div class="stat-number"><?= $availableCount ?></div><div class="stat-foot">Mapped to your department and level</div></article>
  <article class="stat-card"><div class="stat-top"><span>Academic session</span><span class="stat-icon amber-icon">⌂</span></div><div class="stat-number department-number">26/27</div><div class="stat-foot">Semester 1 and 2 course mappings</div></article>
</div>

<section class="panel course-management-panel" id="course-list">
  <div class="panel-heading"><div><h2>Course registration</h2><p class="muted">Default courses are suggested by your department and level. Remove one now and add it back later whenever you need to.</p></div><span class="demo-pill">LIVE DATABASE</span></div>
  <?php if ($courseList === []): ?>
    <div class="empty-state course-empty">No courses have been mapped to your department and level for the 2026/2027 session yet. Ask your administrator to add courses and configure their department, level and semester mappings.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Units</th><th>Type</th><th>Semester</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($courseList as $course): $registered = (int) $course['is_registered'] === 1; ?>
      <tr>
        <td><div class="table-course-code"><?= e($course['course_code']) ?></div><strong><?= e($course['title']) ?></strong><?php if ((int) $course['is_default'] === 1): ?><small class="course-default-label">Department default</small><?php endif; ?></td>
        <td><?= (int) $course['credit_units'] ?></td>
        <td><span class="type-pill"><?= e(ucfirst($course['course_type'])) ?></span></td>
        <td><?= (int) $course['semester'] ?></td>
        <td><span class="status-pill <?= $registered ? 'status-registered' : 'status-available' ?>"><?= $registered ? 'Registered' : 'Available' ?></span></td>
        <td>
          <form method="post" action="courses.php" class="course-action-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
            <input type="hidden" name="semester" value="<?= (int) $course['semester'] ?>">
            <input type="hidden" name="action" value="<?= $registered ? 'remove' : 'add' ?>">
            <button type="submit" class="small-action <?= $registered ? 'remove-course' : 'add-course' ?>"><?= $registered ? 'Remove' : 'Add course' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <div class="panel-bottom"><span class="muted">Your changes are saved to your account.</span><a class="text-link" href="timetable.php">View weekly timetable <span>↗</span></a></div>
</section>

<section class="tip-card course-tip"><div class="tip-icon">✦</div><div><strong>How course allocation works</strong><p>Sync lists courses mapped to your department and level. Default courses can be removed and restored, while other mapped courses can be added to your selection. Your timetable will use your saved courses once the scheduling workflow is connected.</p></div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
