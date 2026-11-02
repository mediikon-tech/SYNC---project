<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();
$user = current_user();

if (($user['role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    exit('Access denied. Only lecturers can schedule classes at this stage.');
}

require_once __DIR__ . '/config/database.php';

$pageTitle = 'Schedule a class';
$activePage = 'schedule_class';
$academicSession = '2026/2027';
$semester = 1;
$error = '';
$success = '';

function sync_valid_time(string $time): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    $day = (string) ($_POST['day_of_week'] ?? '');
    $start = (string) ($_POST['start_time'] ?? '');
    $end = (string) ($_POST['end_time'] ?? '');
    $venue = trim((string) ($_POST['venue'] ?? ''));
    $section = trim((string) ($_POST['class_section'] ?? ''));

    $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$courseId || !in_array($day, $validDays, true) || !sync_valid_time($start) || !sync_valid_time($end) || $end <= $start) {
        $error = 'Enter a valid course, day, start time, and end time. The end time must be later than the start time.';
    } elseif ($venue === '' || strlen($venue) > 120 || $section === '' || strlen($section) > 80) {
        $error = 'Enter a venue (up to 120 characters) and class section (up to 80 characters).';
    } else {
        try {
            $pdo->beginTransaction();

            $permission = $pdo->prepare(
                "SELECT c.id, c.course_code, c.title
                 FROM lecturer_course_requests lcr
                 INNER JOIN courses c ON c.id = lcr.course_id
                 WHERE lcr.lecturer_id = :lecturer_id
                   AND lcr.course_id = :course_id
                   AND lcr.status = 'approved'
                   AND c.is_active = 1
                 FOR UPDATE"
            );
            $permission->execute(['lecturer_id' => $user['id'], 'course_id' => $courseId]);
            $course = $permission->fetch();

            if (!$course) {
                $pdo->rollBack();
                $error = 'You can only schedule courses that an administrator has approved for you.';
            } else {
                $overlap = $pdo->prepare(
                    "SELECT s.id, s.course_id, s.lecturer_id, s.venue, s.class_section,
                            c.course_code, lecturer.full_name AS lecturer_name,
                            CASE
                              WHEN s.lecturer_id = :lecturer_id THEN 'You already have another class at this time'
                              WHEN LOWER(s.venue) = LOWER(:venue) THEN 'The selected venue is already booked'
                              WHEN s.course_id = :course_id AND s.class_section = :class_section THEN 'This course section already has a class at this time'
                              ELSE 'Some students registered for both courses already have a class at this time'
                            END AS reason
                     FROM class_schedules s
                     INNER JOIN courses c ON c.id = s.course_id
                     LEFT JOIN users lecturer ON lecturer.id = s.lecturer_id
                     WHERE s.day_of_week = :day_of_week
                       AND s.academic_session = :academic_session
                       AND s.semester = :semester
                       AND s.status = 'scheduled'
                       AND s.start_time < :end_time
                       AND s.end_time > :start_time
                       AND (
                         s.lecturer_id = :lecturer_id_check
                         OR LOWER(s.venue) = LOWER(:venue_check)
                         OR (s.course_id = :course_id_check AND s.class_section = :class_section_check)
                         OR EXISTS (
                           SELECT 1
                           FROM student_courses current_sc
                           INNER JOIN student_courses existing_sc
                             ON existing_sc.student_id = current_sc.student_id
                            AND existing_sc.academic_session = current_sc.academic_session
                            AND existing_sc.semester = current_sc.semester
                           WHERE current_sc.course_id = :course_id_students
                             AND existing_sc.course_id = s.course_id
                             AND current_sc.academic_session = s.academic_session
                             AND current_sc.semester = s.semester
                         )
                       )
                     FOR UPDATE"
                );
                $overlap->execute([
                    'lecturer_id' => $user['id'],
                    'venue' => $venue,
                    'course_id' => $courseId,
                    'class_section' => $section,
                    'day_of_week' => $day,
                    'academic_session' => $academicSession,
                    'semester' => $semester,
                    'end_time' => $end,
                    'start_time' => $start,
                    'lecturer_id_check' => $user['id'],
                    'venue_check' => $venue,
                    'course_id_check' => $courseId,
                    'class_section_check' => $section,
                    'course_id_students' => $courseId,
                ]);
                $conflicts = $overlap->fetchAll();

                if ($conflicts) {
                    $reasons = array_values(array_unique(array_column($conflicts, 'reason')));
                    $description = 'Schedule attempt by lecturer #' . (int) $user['id']
                        . ' for ' . $course['course_code'] . ' on ' . $day
                        . ' from ' . $start . ' to ' . $end . ' at ' . $venue
                        . '. Conflict: ' . implode('; ', $reasons);
                    $log = $pdo->prepare(
                        "INSERT INTO timetable_conflicts (schedule_id, conflict_type, description, status, reported_by)
                         VALUES (NULL, 'other', :description, 'open', :reported_by)"
                    );
                    $log->execute(['description' => $description, 'reported_by' => $user['id']]);
                    $pdo->commit();
                    $error = 'This class cannot be saved because of a timetable clash: ' . implode('; ', $reasons) . '. The conflict has been recorded for administrator review.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO class_schedules
                         (course_id, lecturer_id, scheduled_by, day_of_week, start_time, end_time,
                          venue, class_section, academic_session, semester, status)
                         VALUES
                         (:course_id, :lecturer_id, :scheduled_by, :day_of_week, :start_time, :end_time,
                          :venue, :class_section, :academic_session, :semester, 'scheduled')"
                    );
                    $insert->execute([
                        'course_id' => $courseId,
                        'lecturer_id' => $user['id'],
                        'scheduled_by' => $user['id'],
                        'day_of_week' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'venue' => $venue,
                        'class_section' => $section,
                        'academic_session' => $academicSession,
                        'semester' => $semester,
                    ]);
                    $pdo->commit();
                    $success = 'Your class has been added to the timetable.';
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Sync class scheduling failed: ' . $exception->getMessage());
            $error = 'The class could not be saved. Check the details and try again.';
        }
    }
}

$approvedQuery = $pdo->prepare(
    "SELECT c.id, c.course_code, c.title
     FROM lecturer_course_requests lcr
     INNER JOIN courses c ON c.id = lcr.course_id
     WHERE lcr.lecturer_id = :lecturer_id
       AND lcr.status = 'approved' AND c.is_active = 1
     ORDER BY c.course_code"
);
$approvedQuery->execute(['lecturer_id' => $user['id']]);
$approvedCourses = $approvedQuery->fetchAll();

$scheduleQuery = $pdo->prepare(
    "SELECT s.id, c.course_code, c.title, s.day_of_week, s.start_time,
            s.end_time, s.venue, s.class_section
     FROM class_schedules s
     INNER JOIN courses c ON c.id = s.course_id
     WHERE s.lecturer_id = :lecturer_id
       AND s.academic_session = :academic_session
       AND s.semester = :semester
       AND s.status = 'scheduled'
     ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time"
);
$scheduleQuery->execute([
    'lecturer_id' => $user['id'],
    'academic_session' => $academicSession,
    'semester' => $semester,
]);
$mySchedules = $scheduleQuery->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div><p class="eyebrow">LECTURER WORKSPACE</p><h1>Schedule a class</h1><p class="muted">Only courses approved by an administrator appear in this form.</p></div>
  <div class="date-chip"><span class="calendar-icon">▦</span><span>2026/2027 · Semester 1</span></div>
</div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>

<section class="panel schedule-form-panel">
  <div class="panel-heading"><div><h2>Class details</h2><p class="muted">The system checks lecturer, venue, course section, and student clashes before saving.</p></div></div>
  <?php if (!$approvedCourses): ?>
    <div class="empty-state">You do not have any approved courses yet. Submit a request from Teaching requests and wait for administrator approval.</div>
    <a class="primary-button" href="lecturer_courses.php">View teaching requests <span>↗</span></a>
  <?php else: ?>
    <form method="post" action="schedule_class.php" class="schedule-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-grid">
        <div class="field-group field-full"><label for="course_id">Approved course</label><select id="course_id" name="course_id" required><option value="">Select a course</option><?php foreach ($approvedCourses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= (string) ($_POST['course_id'] ?? '') === (string) $course['id'] ? 'selected' : '' ?>><?= e($course['course_code'] . ' · ' . $course['title']) ?></option><?php endforeach; ?></select></div>
        <div class="field-group"><label for="day_of_week">Day</label><select id="day_of_week" name="day_of_week" required><option value="">Choose day</option><?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?><option value="<?= e($day) ?>" <?= ($_POST['day_of_week'] ?? '') === $day ? 'selected' : '' ?>><?= e($day) ?></option><?php endforeach; ?></select></div>
        <div class="field-group"><label for="class_section">Class section</label><input id="class_section" name="class_section" maxlength="80" placeholder="e.g. Set A, All students" value="<?= e($_POST['class_section'] ?? 'All students') ?>" required></div>
        <div class="field-group"><label for="start_time">Start time</label><input id="start_time" name="start_time" type="time" value="<?= e($_POST['start_time'] ?? '') ?>" required></div>
        <div class="field-group"><label for="end_time">End time</label><input id="end_time" name="end_time" type="time" value="<?= e($_POST['end_time'] ?? '') ?>" required></div>
        <div class="field-group field-full"><label for="venue">Venue</label><input id="venue" name="venue" maxlength="120" placeholder="e.g. Lecture Theatre 2" value="<?= e($_POST['venue'] ?? '') ?>" required></div>
      </div>
      <div class="schedule-submit-row"><p class="muted">Clashing schedules are rejected and recorded for administrator review.</p><button class="primary-button" type="submit">Save class schedule <span>↗</span></button></div>
    </form>
  <?php endif; ?>
</section>

<section class="panel class-list-panel">
  <div class="panel-heading"><div><h2>My scheduled classes</h2><p class="muted">Your saved classes for this semester.</p></div><span class="demo-pill"><?= count($mySchedules) ?> CLASSES</span></div>
  <?php if (!$mySchedules): ?><div class="empty-state">You have not scheduled any classes yet.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Day</th><th>Time</th><th>Venue</th><th>Section</th></tr></thead><tbody>
    <?php foreach ($mySchedules as $class): ?><tr><td><span class="table-course-code"><?= e($class['course_code']) ?></span><strong><?= e($class['title']) ?></strong></td><td><?= e($class['day_of_week']) ?></td><td><?= e(date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time']))) ?></td><td><?= e($class['venue']) ?></td><td><?= e($class['class_section']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
