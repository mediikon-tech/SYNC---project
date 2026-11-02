<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$user = current_user();
if (($user['role'] ?? '') !== 'course_rep' || empty($user['department_id'])) {
    http_response_code(403);
    exit('Access denied. This page is for assigned department course representatives.');
}

require_once __DIR__ . '/config/database.php';

$pageTitle = 'Representative scheduling';
$activePage = 'rep_schedule';
$academicSession = '2026/2027';
$semester = 1;
$error = '';
$success = '';
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function sync_rep_valid_time(string $time): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
}

// A representative must occupy one of the department's two assigned positions.
$repQuery = $pdo->prepare(
    "SELECT dr.position, d.name AS department_name
     FROM department_representatives dr
     INNER JOIN departments d ON d.id = dr.department_id
     INNER JOIN users u ON u.id = dr.representative_user_id
     WHERE dr.representative_user_id = :user_id
       AND dr.department_id = :department_id
       AND u.role = 'course_rep'
       AND u.is_active = 1"
);
$repQuery->execute(['user_id' => $user['id'], 'department_id' => $user['department_id']]);
$representative = $repQuery->fetch();

if (!$representative) {
    http_response_code(403);
    exit('Your department has not assigned you one of its two course representative positions.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    $day = (string) ($_POST['day_of_week'] ?? '');
    $start = (string) ($_POST['start_time'] ?? '');
    $end = (string) ($_POST['end_time'] ?? '');
    $venue = trim((string) ($_POST['venue'] ?? ''));
    $section = trim((string) ($_POST['class_section'] ?? 'All students'));

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$courseId || !in_array($day, $days, true) || !sync_rep_valid_time($start) || !sync_rep_valid_time($end) || $end <= $start) {
        $error = 'Choose a valid course, day, start time and end time.';
    } elseif ($venue === '' || strlen($venue) > 120 || $section === '' || strlen($section) > 80) {
        $error = 'Enter a venue and class section within the stated character limits.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock the course row while checking that no lecturer or other rep has scheduled it.
            $courseQuery = $pdo->prepare(
                "SELECT id, course_code, title
                 FROM courses
                 WHERE id = :course_id
                   AND department_id = :department_id
                   AND is_active = 1
                 FOR UPDATE"
            );
            $courseQuery->execute(['course_id' => $courseId, 'department_id' => $user['department_id']]);
            $course = $courseQuery->fetch();

            $alreadyScheduled = false;
            if ($course) {
                $existingCourse = $pdo->prepare(
                    "SELECT id FROM class_schedules
                     WHERE course_id = :course_id
                       AND academic_session = :academic_session
                       AND semester = :semester
                       AND status = 'scheduled'
                     LIMIT 1 FOR UPDATE"
                );
                $existingCourse->execute([
                    'course_id' => $courseId,
                    'academic_session' => $academicSession,
                    'semester' => $semester,
                ]);
                $alreadyScheduled = (bool) $existingCourse->fetch();
            }

            if (!$course) {
                $pdo->rollBack();
                $error = 'You can only schedule active courses in your own department.';
            } elseif ($alreadyScheduled) {
                $pdo->rollBack();
                $error = 'This course already has a scheduled class. Representatives cannot replace a lecturer schedule or create a duplicate.';
            } else {
                $overlap = $pdo->prepare(
                    "SELECT s.id, s.venue,
                            CASE
                              WHEN LOWER(s.venue) = LOWER(:venue) THEN 'The selected venue is already booked'
                              WHEN s.course_id = :course_id AND s.class_section = :section THEN 'This course section already has a class'
                              ELSE 'Some students registered for both courses already have a class at this time'
                            END AS reason
                     FROM class_schedules s
                     WHERE s.day_of_week = :day
                       AND s.academic_session = :session
                       AND s.semester = :semester
                       AND s.status = 'scheduled'
                       AND s.start_time < :end_time
                       AND s.end_time > :start_time
                       AND (
                         LOWER(s.venue) = LOWER(:venue_check)
                         OR (s.course_id = :course_id_check AND s.class_section = :section_check)
                         OR EXISTS (
                           SELECT 1
                           FROM student_courses target_sc
                           INNER JOIN student_courses existing_sc
                             ON existing_sc.student_id = target_sc.student_id
                            AND existing_sc.academic_session = target_sc.academic_session
                            AND existing_sc.semester = target_sc.semester
                           WHERE target_sc.course_id = :target_course
                             AND existing_sc.course_id = s.course_id
                             AND target_sc.academic_session = s.academic_session
                             AND target_sc.semester = s.semester
                         )
                       )
                     FOR UPDATE"
                );
                $overlap->execute([
                    'venue' => $venue,
                    'course_id' => $courseId,
                    'section' => $section,
                    'day' => $day,
                    'session' => $academicSession,
                    'semester' => $semester,
                    'end_time' => $end,
                    'start_time' => $start,
                    'venue_check' => $venue,
                    'course_id_check' => $courseId,
                    'section_check' => $section,
                    'target_course' => $courseId,
                ]);
                $conflicts = $overlap->fetchAll();

                if ($conflicts) {
                    $reasons = array_values(array_unique(array_column($conflicts, 'reason')));
                    $description = 'Course representative #' . (int) $user['id']
                        . ' attempted to schedule ' . $course['course_code'] . ' on ' . $day
                        . ' from ' . $start . ' to ' . $end . ' at ' . $venue
                        . '. Conflict: ' . implode('; ', $reasons);
                    $log = $pdo->prepare(
                        "INSERT INTO timetable_conflicts (schedule_id, conflict_type, description, status, reported_by)
                         VALUES (NULL, 'other', :description, 'open', :reported_by)"
                    );
                    $log->execute(['description' => $description, 'reported_by' => $user['id']]);
                    $pdo->commit();
                    $error = 'This class has a clash and was not saved. The conflict was sent for administrator review.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO class_schedules
                         (course_id, lecturer_id, scheduled_by, day_of_week, start_time, end_time,
                          venue, class_section, academic_session, semester, status)
                         VALUES
                         (:course_id, NULL, :scheduled_by, :day, :start_time, :end_time,
                          :venue, :section, :session, :semester, 'scheduled')"
                    );
                    $insert->execute([
                        'course_id' => $courseId,
                        'scheduled_by' => $user['id'],
                        'day' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'venue' => $venue,
                        'section' => $section,
                        'session' => $academicSession,
                        'semester' => $semester,
                    ]);
                    $pdo->commit();
                    $success = 'The class has been added to the student timetable. A lecturer can be assigned later.';
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Sync representative scheduling failed: ' . $exception->getMessage());
            $error = 'The class could not be saved. Check the details and try again.';
        }
    }
}

$availableQuery = $pdo->prepare(
    "SELECT c.id, c.course_code, c.title
     FROM courses c
     WHERE c.department_id = :department_id
       AND c.is_active = 1
       AND NOT EXISTS (
         SELECT 1 FROM class_schedules s
         WHERE s.course_id = c.id
           AND s.academic_session = :session
           AND s.semester = :semester
           AND s.status = 'scheduled'
       )
     ORDER BY c.course_code"
);
$availableQuery->execute([
    'department_id' => $user['department_id'],
    'session' => $academicSession,
    'semester' => $semester,
]);
$availableCourses = $availableQuery->fetchAll();

$myQuery = $pdo->prepare(
    "SELECT c.course_code, c.title, s.day_of_week, s.start_time, s.end_time,
            s.venue, s.class_section
     FROM class_schedules s
     INNER JOIN courses c ON c.id = s.course_id
     WHERE s.scheduled_by = :user_id
       AND s.lecturer_id IS NULL
       AND s.academic_session = :session
       AND s.semester = :semester
       AND s.status = 'scheduled'
     ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time"
);
$myQuery->execute(['user_id' => $user['id'], 'session' => $academicSession, 'semester' => $semester]);
$mySchedules = $myQuery->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div><p class="eyebrow">COURSE REPRESENTATIVE WORKSPACE</p><h1>Schedule an unscheduled course</h1><p class="muted"><?= e($representative['department_name']) ?> · Position <?= (int) $representative['position'] ?> of 2</p></div>
  <div class="date-chip"><span class="calendar-icon">▦</span><span>2026/2027 · Semester 1</span></div>
</div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>

<section class="panel schedule-form-panel">
  <div class="panel-heading"><div><h2>Fallback class details</h2><p class="muted">Only active department courses without an existing scheduled class are available. This cannot override a lecturer's schedule.</p></div></div>
  <?php if (!$availableCourses): ?>
    <div class="empty-state">All active courses in your department have already been scheduled for this semester.</div>
  <?php else: ?>
    <form method="post" action="rep_schedule.php" class="schedule-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-grid">
        <div class="field-group field-full"><label for="course_id">Unscheduled department course</label><select id="course_id" name="course_id" required><option value="">Select a course</option><?php foreach ($availableCourses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= (string) ($_POST['course_id'] ?? '') === (string) $course['id'] ? 'selected' : '' ?>><?= e($course['course_code'] . ' · ' . $course['title']) ?></option><?php endforeach; ?></select></div>
        <div class="field-group"><label for="day_of_week">Day</label><select id="day_of_week" name="day_of_week" required><option value="">Choose day</option><?php foreach ($days as $day): ?><option value="<?= e($day) ?>" <?= ($_POST['day_of_week'] ?? '') === $day ? 'selected' : '' ?>><?= e($day) ?></option><?php endforeach; ?></select></div>
        <div class="field-group"><label for="class_section">Class section</label><input id="class_section" name="class_section" maxlength="80" value="<?= e($_POST['class_section'] ?? 'All students') ?>" required></div>
        <div class="field-group"><label for="start_time">Start time</label><input id="start_time" name="start_time" type="time" value="<?= e($_POST['start_time'] ?? '') ?>" required></div>
        <div class="field-group"><label for="end_time">End time</label><input id="end_time" name="end_time" type="time" value="<?= e($_POST['end_time'] ?? '') ?>" required></div>
        <div class="field-group field-full"><label for="venue">Venue</label><input id="venue" name="venue" maxlength="120" placeholder="e.g. Lecture Theatre 2" value="<?= e($_POST['venue'] ?? '') ?>" required></div>
      </div>
      <div class="schedule-submit-row"><p class="muted">Clashes are rejected and recorded for administrator review.</p><button class="primary-button" type="submit">Add class to timetable <span>↗</span></button></div>
    </form>
  <?php endif; ?>
</section>

<section class="panel class-list-panel">
  <div class="panel-heading"><div><h2>Classes I scheduled</h2><p class="muted">These fallback classes are visible to students registered for the courses.</p></div><span class="demo-pill"><?= count($mySchedules) ?> CLASSES</span></div>
  <?php if (!$mySchedules): ?><div class="empty-state">You have not scheduled any fallback classes yet.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Day</th><th>Time</th><th>Venue</th><th>Section</th></tr></thead><tbody>
    <?php foreach ($mySchedules as $class): ?><tr><td><span class="table-course-code"><?= e($class['course_code']) ?></span><strong><?= e($class['title']) ?></strong></td><td><?= e($class['day_of_week']) ?></td><td><?= e(date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time']))) ?></td><td><?= e($class['venue']) ?></td><td><?= e($class['class_section']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
