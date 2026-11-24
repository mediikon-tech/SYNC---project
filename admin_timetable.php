<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$user = current_user();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrator permission is required.');
}

require_once __DIR__ . '/config/database.php';

$pageTitle = 'Timetable generation';
$activePage = 'admin_timetable';
require_once __DIR__ . '/includes/academic.php';
$activeTerm = sync_current_academic_term($pdo);
$session = $activeTerm['academic_session'];
$semester = $activeTerm['semester'];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$error = '';
$success = '';
$generationSummary = null;
$venueOptions = $pdo->query('SELECT name FROM venues WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

function sync_generator_time_valid(string $time): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'add_slot') {
        $day = (string) ($_POST['day_of_week'] ?? '');
        $start = (string) ($_POST['start_time'] ?? '');
        $end = (string) ($_POST['end_time'] ?? '');
        $venue = trim((string) ($_POST['venue'] ?? ''));

        if (!in_array($day, $days, true) || !sync_generator_time_valid($start) ||
            !sync_generator_time_valid($end) || $end <= $start ||
            !in_array($venue, $venueOptions, true)) {
            $error = 'Choose an active venue and a valid day and time range. The end time must be later than the start time.';
        } else {
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO timetable_availability
                     (day_of_week, start_time, end_time, venue, academic_session, semester, created_by)
                     VALUES (:day, :start_time, :end_time, :venue, :session, :semester, :created_by)"
                );
                $insert->execute([
                    'day' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'venue' => $venue,
                    'session' => $session,
                    'semester' => $semester,
                    'created_by' => $user['id'],
                ]);
                $success = 'The available time and venue have been added.';
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'That exact day, time, and venue combination already exists.';
                } else {
                    error_log('Sync availability insert failed: ' . $exception->getMessage());
                    $error = 'The availability slot could not be saved.';
                }
            }
        }
    } elseif ($action === 'update_slot') {
        $slotId = filter_var($_POST['slot_id'] ?? null, FILTER_VALIDATE_INT);
        $day = (string) ($_POST['day_of_week'] ?? '');
        $start = (string) ($_POST['start_time'] ?? '');
        $end = (string) ($_POST['end_time'] ?? '');
        $venue = trim((string) ($_POST['venue'] ?? ''));
        if (!$slotId || !in_array($day, $days, true) || !sync_generator_time_valid($start) ||
            !sync_generator_time_valid($end) || $end <= $start || !in_array($venue, $venueOptions, true)) {
            $error = 'Choose a valid day, time range, and active venue for this period.';
        } else {
            try {
                $update = $pdo->prepare(
                    'UPDATE timetable_availability SET day_of_week=?, start_time=?, end_time=?, venue=?
                     WHERE id=? AND academic_session=? AND semester=?'
                );
                $update->execute([$day,$start,$end,$venue,$slotId,$session,$semester]);
                $success = $update->rowCount() ? 'Available period updated.' : 'No changes were made, or that period was not found.';
            } catch (PDOException $exception) {
                $error = $exception->getCode() === '23000'
                    ? 'That exact day, time, and venue combination already exists.'
                    : 'The available period could not be updated.';
            }
        }
    } elseif ($action === 'delete_slot') {
        $slotId = filter_var($_POST['slot_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$slotId) {
            $error = 'Choose a valid availability slot.';
        } else {
            $delete = $pdo->prepare(
                'DELETE FROM timetable_availability WHERE id = :id AND academic_session = :session AND semester = :semester'
            );
            $delete->execute(['id' => $slotId, 'session' => $session, 'semester' => $semester]);
            $success = $delete->rowCount() ? 'Availability slot removed.' : 'That slot was not found.';
        }
    } elseif ($action === 'generate') {
        try {
            $pdo->beginTransaction();

            $slotQuery = $pdo->prepare(
                "SELECT ta.id, ta.day_of_week, ta.start_time, ta.end_time, ta.venue, v.capacity
                 FROM timetable_availability ta
                 INNER JOIN venues v ON v.name = ta.venue AND v.is_active = 1
                 WHERE ta.academic_session = :session AND ta.semester = :semester AND ta.is_active = 1
                 ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time, venue
                 FOR UPDATE"
            );
            $slotQuery->execute(['session' => $session, 'semester' => $semester]);
            $slots = $slotQuery->fetchAll();

            if (!$slots) {
                throw new RuntimeException('Add at least one available time and venue before generating the timetable.');
            }

            $courseQuery = $pdo->prepare(
                "SELECT c.id, c.course_code, c.title, COUNT(DISTINCT sc.student_id) AS student_count
                 FROM courses c
                 INNER JOIN student_courses sc
                   ON sc.course_id = c.id
                  AND sc.academic_session = :student_session
                  AND sc.semester = :student_semester
                 WHERE c.is_active = 1
                   AND EXISTS (
                     SELECT 1 FROM lecturer_course_requests lcr
                     WHERE lcr.course_id = c.id AND lcr.status = 'approved'
                   )
                 GROUP BY c.id, c.course_code, c.title
                 ORDER BY student_count DESC, c.course_code"
            );
            $courseQuery->execute(['student_session' => $session, 'student_semester' => $semester]);
            $courses = $courseQuery->fetchAll();

            $enrolmentQuery = $pdo->prepare(
                'SELECT student_id FROM student_courses
                 WHERE course_id = :course_id AND academic_session = :session AND semester = :semester'
            );
            $lecturerQuery = $pdo->prepare(
                "SELECT u.id, u.full_name
                 FROM lecturer_course_requests lcr
                 INNER JOIN users u ON u.id = lcr.lecturer_id
                 WHERE lcr.course_id = :course_id
                   AND lcr.status = 'approved' AND u.is_active = 1
                 ORDER BY u.id"
            );

            $existingQuery = $pdo->prepare(
                "SELECT id, course_id, lecturer_id, day_of_week, start_time, end_time, venue
                 FROM class_schedules
                 WHERE academic_session = :session AND semester = :semester AND status = 'scheduled'
                 FOR UPDATE"
            );
            $existingQuery->execute(['session' => $session, 'semester' => $semester]);
            $planned = $existingQuery->fetchAll();

            $studentMap = [];
            $allCourseIds = array_unique(array_merge(
                array_map(static fn($row) => (int) $row['course_id'], $planned),
                array_map(static fn($row) => (int) $row['id'], $courses)
            ));
            foreach ($allCourseIds as $courseId) {
                $enrolmentQuery->execute([
                    'course_id' => $courseId,
                    'session' => $session,
                    'semester' => $semester,
                ]);
                $studentMap[$courseId] = array_map('intval', $enrolmentQuery->fetchAll(PDO::FETCH_COLUMN));
            }

            $scheduledCourseIds = [];
            $lecturerLoads = [];
            foreach ($planned as $item) {
                $scheduledCourseIds[(int) $item['course_id']] = true;
                if ($item['lecturer_id'] !== null) {
                    $lecturerId = (int) $item['lecturer_id'];
                    $lecturerLoads[$lecturerId] = ($lecturerLoads[$lecturerId] ?? 0) + 1;
                }
            }

            $insertSchedule = $pdo->prepare(
                "INSERT INTO class_schedules
                 (course_id, lecturer_id, scheduled_by, day_of_week, start_time, end_time,
                  venue, class_section, academic_session, semester, status)
                 VALUES
                 (:course_id, :lecturer_id, :scheduled_by, :day, :start_time, :end_time,
                  :venue, 'All students', :session, :semester, 'scheduled')"
            );
            $insertConflict = $pdo->prepare(
                "INSERT INTO timetable_conflicts
                 (schedule_id, conflict_type, description, status, reported_by)
                 VALUES (NULL, 'time_range', :description, 'open', :reported_by)"
            );

            $created = 0;
            $skippedAlreadyScheduled = 0;
            $unplaced = [];
            foreach ($courses as $course) {
                $courseId = (int) $course['id'];
                if (isset($scheduledCourseIds[$courseId])) {
                    $skippedAlreadyScheduled++;
                    continue;
                }

                $lecturerQuery->execute(['course_id' => $courseId]);
                $lecturers = $lecturerQuery->fetchAll();
                if (!$lecturers) {
                    continue;
                }
                usort($lecturers, static function (array $a, array $b) use (&$lecturerLoads): int {
                    return ($lecturerLoads[(int) $a['id']] ?? 0) <=> ($lecturerLoads[(int) $b['id']] ?? 0);
                });

                $courseStudents = $studentMap[$courseId] ?? [];
                $chosen = null;
                foreach ($slots as $slot) {
                    if ($slot['capacity'] !== null && (int) $slot['capacity'] < (int) $course['student_count']) {
                        continue;
                    }
                    foreach ($lecturers as $lecturer) {
                        $lecturerId = (int) $lecturer['id'];
                        $hasClash = false;
                        foreach ($planned as $other) {
                            if ($other['day_of_week'] !== $slot['day_of_week'] ||
                                $other['start_time'] >= $slot['end_time'] ||
                                $other['end_time'] <= $slot['start_time']) {
                                continue;
                            }
                            if (strcasecmp((string) $other['venue'], (string) $slot['venue']) === 0 ||
                                (int) $other['course_id'] === $courseId ||
                                ($other['lecturer_id'] !== null && (int) $other['lecturer_id'] === $lecturerId)) {
                                $hasClash = true;
                                break;
                            }
                            $otherStudents = $studentMap[(int) $other['course_id']] ?? [];
                            if ($courseStudents && $otherStudents && array_intersect($courseStudents, $otherStudents)) {
                                $hasClash = true;
                                break;
                            }
                        }
                        if (!$hasClash) {
                            $chosen = ['slot' => $slot, 'lecturer_id' => $lecturerId];
                            break 2;
                        }
                    }
                }

                if ($chosen === null) {
                    $unplaced[] = $course['course_code'];
                    $insertConflict->execute([
                        'description' => 'Automatic generation could not place ' . $course['course_code']
                            . ' because no available slot and venue passed the lecturer, venue, course, and student-group clash checks.',
                        'reported_by' => $user['id'],
                    ]);
                    continue;
                }

                $slot = $chosen['slot'];
                $insertSchedule->execute([
                    'course_id' => $courseId,
                    'lecturer_id' => $chosen['lecturer_id'],
                    'scheduled_by' => $user['id'],
                    'day' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'venue' => $slot['venue'],
                    'session' => $session,
                    'semester' => $semester,
                ]);
                $newId = (int) $pdo->lastInsertId();
                $planned[] = [
                    'id' => $newId,
                    'course_id' => $courseId,
                    'lecturer_id' => $chosen['lecturer_id'],
                    'day_of_week' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'venue' => $slot['venue'],
                ];
                $scheduledCourseIds[$courseId] = true;
                $lecturerLoads[$chosen['lecturer_id']] = ($lecturerLoads[$chosen['lecturer_id']] ?? 0) + 1;
                $created++;
            }

            $pdo->commit();
            $generationSummary = [
                'created' => $created,
                'already' => $skippedAlreadyScheduled,
                'unplaced' => $unplaced,
            ];
            $success = 'Timetable generation completed. ' . $created . ' class(es) added.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Sync automatic timetable generation failed: ' . $exception->getMessage());
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The timetable could not be generated. Check the database and try again.';
        }
    }
}

$availabilityQuery = $pdo->prepare(
    "SELECT id, day_of_week, start_time, end_time, venue
     FROM timetable_availability
     WHERE academic_session = :session AND semester = :semester
     ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time, venue"
);
$availabilityQuery->execute(['session' => $session, 'semester' => $semester]);
$availability = $availabilityQuery->fetchAll();

$scheduledQuery = $pdo->prepare(
    "SELECT COUNT(*) FROM class_schedules
     WHERE academic_session = :session AND semester = :semester AND status = 'scheduled'"
);
$scheduledQuery->execute(['session' => $session, 'semester' => $semester]);
$scheduledCount = (int) $scheduledQuery->fetchColumn();

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div>
    <p class="eyebrow">ADMINISTRATION</p>
    <h1>Timetable generation</h1>
    <p class="muted">Define the available periods and venues, then generate a clash-checked timetable.</p>
  </div>
  <div class="date-chip"><span class="calendar-icon">▦</span><span><?= e($session) ?> · Semester <?= $semester ?></span></div>
</div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>

<?php if ($generationSummary !== null): ?>
<section class="panel">
  <div class="panel-heading"><div><h2>Generation report</h2><p class="muted">Existing schedules were preserved. Only courses without a current scheduled class were considered.</p></div></div>
  <div class="timetable-summary">
    <div class="summary-item"><div><small>New classes</small><strong><?= (int) $generationSummary['created'] ?></strong></div></div>
    <div class="summary-item"><div><small>Already scheduled</small><strong><?= (int) $generationSummary['already'] ?></strong></div></div>
    <div class="summary-item"><div><small>Could not place</small><strong><?= count($generationSummary['unplaced']) ?></strong></div></div>
  </div>
  <?php if ($generationSummary['unplaced']): ?><p class="form-feedback">No valid slot was found for: <?= e(implode(', ', $generationSummary['unplaced'])) ?>. These cases were sent to conflict management.</p><?php endif; ?>
</section>
<?php endif; ?>

<section class="panel schedule-form-panel">
  <div class="panel-heading"><div><h2>Add an available period</h2><p class="muted">Each entry defines one bookable period at one venue. Add multiple venues and periods as needed.</p></div></div>
  <form method="post" action="admin_timetable.php" class="schedule-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_slot">
    <div class="form-grid">
      <div class="field-group"><label for="day_of_week">Day</label><select id="day_of_week" name="day_of_week" required><?php foreach ($days as $day): ?><option value="<?= e($day) ?>"><?= e($day) ?></option><?php endforeach; ?></select></div>
      <div class="field-group"><label for="venue">Venue</label><select id="venue" name="venue" required><option value="">Choose active venue</option><?php foreach ($venueOptions as $venueOption): ?><option value="<?= e($venueOption) ?>"><?= e($venueOption) ?></option><?php endforeach; ?></select><small class="muted"><a href="admin_setup.php">Manage teaching venues</a></small></div>
      <div class="field-group"><label for="start_time">Start time</label><input id="start_time" name="start_time" type="time" required></div>
      <div class="field-group"><label for="end_time">End time</label><input id="end_time" name="end_time" type="time" required></div>
    </div>
    <button class="primary-button" type="submit">Add available period</button>
  </form>
</section>

<section class="panel class-list-panel">
  <div class="panel-heading"><div><h2>Available periods and venues</h2><p class="muted">These are the only time and venue combinations the generator will use.</p></div><span class="demo-pill"><?= count($availability) ?> PERIODS</span></div>
  <?php if (!$availability): ?>
    <div class="empty-state">No periods have been configured. Add your institution's available periods before generating a timetable.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Day</th><th>Time</th><th>Venue</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($availability as $slot): ?><tr><td colspan="4">
        <form method="post" action="admin_timetable.php" class="inline-edit-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_slot">
          <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
          <select name="day_of_week"><?php foreach ($days as $day): ?><option value="<?= e($day) ?>" <?= $slot['day_of_week'] === $day ? 'selected' : '' ?>><?= e($day) ?></option><?php endforeach; ?></select>
          <input name="start_time" type="time" value="<?= e(substr($slot['start_time'],0,5)) ?>" required>
          <input name="end_time" type="time" value="<?= e(substr($slot['end_time'],0,5)) ?>" required>
          <select name="venue"><?php foreach ($venueOptions as $venueOption): ?><option value="<?= e($venueOption) ?>" <?= $slot['venue'] === $venueOption ? 'selected' : '' ?>><?= e($venueOption) ?></option><?php endforeach; ?></select>
          <button class="small-action add-course" type="submit">Save</button>
        </form>
        <form method="post" action="admin_timetable.php" onsubmit="return confirm('Remove this available period?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_slot"><input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>"><button class="small-action remove-course" type="submit">Remove period</button></form>
      </td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="schedule-submit-row">
      <p class="muted"><?= $scheduledCount ?> existing scheduled class(es) will be kept. Courses without an approved lecturer remain available for representative scheduling.</p>
      <form method="post" action="admin_timetable.php" onsubmit="return confirm('Generate classes for courses that are not already scheduled? Existing classes will remain unchanged.');">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="generate">
        <button class="primary-button" type="submit">Generate timetable</button>
      </form>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
