<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();
$user = current_user();

if (($user['role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    exit('Access denied. This page is for lecturer accounts.');
}

require_once __DIR__ . '/config/database.php';

$pageTitle = 'My teaching requests';
$activePage = 'lecturer_courses';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$courseId || empty($user['department_id'])) {
        $error = 'Choose a valid course from your department.';
    } else {
        try {
            $courseCheck = $pdo->prepare('SELECT id FROM courses WHERE id = :course_id AND department_id = :department_id AND is_active = 1');
            $courseCheck->execute(['course_id' => $courseId, 'department_id' => $user['department_id']]);
            if (!$courseCheck->fetch()) {
                $error = 'That course is not available in your department.';
            } else {
                $save = $pdo->prepare(
                    "INSERT INTO lecturer_course_requests (lecturer_id, course_id, status)
                     VALUES (:lecturer_id, :course_id, 'pending')
                     ON DUPLICATE KEY UPDATE
                       status = IF(status = 'rejected', 'pending', status),
                       reviewed_by = IF(status = 'rejected', NULL, reviewed_by),
                       reviewed_at = IF(status = 'rejected', NULL, reviewed_at),
                       review_note = IF(status = 'rejected', NULL, review_note)"
                );
                $save->execute(['lecturer_id' => $user['id'], 'course_id' => $courseId]);
                $success = 'Your course request has been submitted. An administrator must approve it before you can schedule classes.';
            }
        } catch (PDOException $exception) {
            error_log('Sync lecturer request failed: ' . $exception->getMessage());
            $error = 'The request could not be saved. Please try again.';
        }
    }
}

$courseQuery = $pdo->prepare(
    "SELECT c.id, c.course_code, c.title, c.credit_units,
            lcr.status, lcr.review_note, lcr.created_at, lcr.reviewed_at
     FROM courses c
     LEFT JOIN lecturer_course_requests lcr
       ON lcr.course_id = c.id AND lcr.lecturer_id = :lecturer_id
     WHERE c.department_id = :department_id AND c.is_active = 1
     ORDER BY c.course_code"
);
$courseQuery->execute(['lecturer_id' => $user['id'], 'department_id' => $user['department_id']]);
$courses = $courseQuery->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div><p class="eyebrow">LECTURER WORKSPACE</p><h1>Teaching course requests</h1><p class="muted">Request the courses you are qualified to teach. Approval is required before scheduling.</p></div>
</div>
<section class="panel course-management-panel">
  <div class="panel-heading"><div><h2>Courses in your department</h2><p class="muted">Submit a request or check its current status.</p></div></div>
  <?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>
  <?php if (!$courses): ?>
    <div class="empty-state">No active courses are currently listed for your department. Contact the administrator.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table">
      <thead><tr><th>Course</th><th>Credit units</th><th>Request status</th><th>Administrator note</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($courses as $course): ?>
        <tr>
          <td><span class="table-course-code"><?= e($course['course_code']) ?></span><strong><?= e($course['title']) ?></strong></td>
          <td><?= (int) $course['credit_units'] ?></td>
          <td><span class="status-pill <?= e('status-' . ($course['status'] ?? 'available')) ?>"><?= e(ucfirst($course['status'] ?? 'not requested')) ?></span></td>
          <td><?= e($course['review_note'] ?: 'No note') ?></td>
          <td>
            <?php if (($course['status'] ?? '') === 'approved'): ?>
              <span class="muted">Approved for teaching</span>
            <?php elseif (($course['status'] ?? '') === 'pending'): ?>
              <span class="muted">Awaiting review</span>
            <?php else: ?>
              <form method="post" action="lecturer_courses.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                <button class="small-action add-course" type="submit"><?= ($course['status'] ?? '') === 'rejected' ? 'Request again' : 'Request course' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
