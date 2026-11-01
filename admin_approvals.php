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

$pageTitle = 'Lecturer approvals';
$activePage = 'admin_approvals';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$requestId || !in_array($decision, ['approved', 'rejected'], true)) {
        $error = 'Choose a valid approval decision.';
    } elseif (strlen($note) > 500) {
        $error = 'The administrator note must be 500 characters or fewer.';
    } else {
        try {
            $update = $pdo->prepare(
                "UPDATE lecturer_course_requests
                 SET status = :status, reviewed_by = :reviewed_by,
                     reviewed_at = NOW(), review_note = :review_note
                 WHERE id = :request_id AND status = 'pending'"
            );
            $update->execute([
                'status' => $decision,
                'reviewed_by' => $user['id'],
                'review_note' => $note !== '' ? $note : null,
                'request_id' => $requestId,
            ]);
            $success = $update->rowCount() === 1
                ? 'The lecturer course request has been ' . $decision . '.'
                : 'That request has already been reviewed or no longer exists.';
        } catch (PDOException $exception) {
            error_log('Sync approval update failed: ' . $exception->getMessage());
            $error = 'The decision could not be saved. Please try again.';
        }
    }
}

$requests = $pdo->query(
    "SELECT lcr.id, lcr.status, lcr.created_at, lcr.review_note,
            lecturer.full_name AS lecturer_name, lecturer.email AS lecturer_email,
            d.name AS department_name, c.course_code, c.title
     FROM lecturer_course_requests lcr
     INNER JOIN users lecturer ON lecturer.id = lcr.lecturer_id
     INNER JOIN courses c ON c.id = lcr.course_id
     LEFT JOIN departments d ON d.id = lecturer.department_id
     ORDER BY CASE WHEN lcr.status = 'pending' THEN 0 ELSE 1 END, lcr.created_at DESC"
)->fetchAll();

$pendingCount = 0;
foreach ($requests as $request) {
    if ($request['status'] === 'pending') $pendingCount++;
}

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div><p class="eyebrow">ADMINISTRATION</p><h1>Lecturer course approvals</h1><p class="muted">Review lecturer requests before allowing them to schedule classes.</p></div>
  <div class="date-chip"><span class="calendar-icon">✓</span><span><?= $pendingCount ?> pending request<?= $pendingCount === 1 ? '' : 's' ?></span></div>
</div>
<section class="panel course-management-panel">
  <div class="panel-heading"><div><h2>All lecturer requests</h2><p class="muted">Only pending requests can be approved or rejected.</p></div></div>
  <?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>
  <?php if (!$requests): ?>
    <div class="empty-state">There are no lecturer course requests yet.</div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table">
      <thead><tr><th>Lecturer</th><th>Department</th><th>Requested course</th><th>Status</th><th>Review decision</th></tr></thead>
      <tbody>
      <?php foreach ($requests as $request): ?>
        <tr>
          <td><strong><?= e($request['lecturer_name']) ?></strong><span class="muted"><?= e($request['lecturer_email']) ?></span></td>
          <td><?= e($request['department_name'] ?? 'Not assigned') ?></td>
          <td><span class="table-course-code"><?= e($request['course_code']) ?></span><strong><?= e($request['title']) ?></strong></td>
          <td><span class="status-pill <?= e('status-' . $request['status']) ?>"><?= e(ucfirst($request['status'])) ?></span><?php if ($request['review_note']): ?><span class="muted"><?= e($request['review_note']) ?></span><?php endif; ?></td>
          <td>
            <?php if ($request['status'] === 'pending'): ?>
              <form method="post" action="admin_approvals.php" class="approval-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                <input type="text" name="review_note" maxlength="500" placeholder="Optional note">
                <div class="approval-actions">
                  <button class="small-action add-course" type="submit" name="decision" value="approved">Approve</button>
                  <button class="small-action remove-course" type="submit" name="decision" value="rejected">Reject</button>
                </div>
              </form>
            <?php else: ?>
              <span class="muted">Reviewed</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
