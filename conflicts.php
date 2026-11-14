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

$pageTitle = 'Timetable conflicts';
$activePage = 'conflicts';
$error = '';
$success = '';

$allowedStatuses = ['open', 'reviewing', 'resolved', 'unavoidable'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conflictId = filter_var($_POST['conflict_id'] ?? null, FILTER_VALIDATE_INT);
    $newStatus = (string) ($_POST['status'] ?? '');
    $note = trim((string) ($_POST['resolution_note'] ?? ''));

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$conflictId || !in_array($newStatus, $allowedStatuses, true)) {
        $error = 'Choose a valid conflict and review status.';
    } elseif (strlen($note) > 3000) {
        $error = 'The review note must be 3,000 characters or fewer.';
    } elseif (in_array($newStatus, ['resolved', 'unavoidable'], true) && $note === '') {
        $error = 'Add a review note before closing a conflict as resolved or unavoidable.';
    } else {
        try {
            $update = $pdo->prepare(
                "UPDATE timetable_conflicts
                 SET status = :status,
                     resolution_note = :resolution_note,
                     resolved_by = :resolved_by,
                     resolved_at = :resolved_at
                 WHERE id = :conflict_id"
            );
            $isClosed = in_array($newStatus, ['resolved', 'unavoidable'], true);
            $update->execute([
                'status' => $newStatus,
                'resolution_note' => $note !== '' ? $note : null,
                'resolved_by' => $isClosed ? $user['id'] : null,
                'resolved_at' => $isClosed ? date('Y-m-d H:i:s') : null,
                'conflict_id' => $conflictId,
            ]);

            $success = $update->rowCount() > 0
                ? 'Conflict #' . $conflictId . ' has been updated.'
                : 'No changes were made. The conflict may already have this status.';
        } catch (PDOException $exception) {
            error_log('Sync conflict review failed: ' . $exception->getMessage());
            $error = 'The conflict could not be updated. Please try again.';
        }
    }
}

$filter = (string) ($_GET['status'] ?? 'all');
if ($filter !== 'all' && !in_array($filter, $allowedStatuses, true)) {
    $filter = 'all';
}

$counts = array_fill_keys($allowedStatuses, 0);
$countRows = $pdo->query(
    "SELECT status, COUNT(*) AS total
     FROM timetable_conflicts
     GROUP BY status"
)->fetchAll();
foreach ($countRows as $countRow) {
    if (isset($counts[$countRow['status']])) {
        $counts[$countRow['status']] = (int) $countRow['total'];
    }
}
$totalConflicts = array_sum($counts);

$sql = "
    SELECT tc.id, tc.conflict_type, tc.description, tc.status,
           tc.resolution_note, tc.created_at, tc.resolved_at,
           reporter.full_name AS reporter_name,
           resolver.full_name AS resolver_name,
           c.course_code, c.title AS course_title,
           s.day_of_week, s.start_time, s.end_time, s.venue,
           s.class_section, s.academic_session, s.semester
    FROM timetable_conflicts tc
    LEFT JOIN users reporter ON reporter.id = tc.reported_by
    LEFT JOIN users resolver ON resolver.id = tc.resolved_by
    LEFT JOIN class_schedules s ON s.id = tc.schedule_id
    LEFT JOIN courses c ON c.id = s.course_id
";
$params = [];
if ($filter !== 'all') {
    $sql .= " WHERE tc.status = :status";
    $params['status'] = $filter;
}
$sql .= " ORDER BY
    CASE tc.status
      WHEN 'open' THEN 1
      WHEN 'reviewing' THEN 2
      WHEN 'unavoidable' THEN 3
      WHEN 'resolved' THEN 4
      ELSE 5
    END,
    tc.created_at DESC";

$listQuery = $pdo->prepare($sql);
$listQuery->execute($params);
$conflicts = $listQuery->fetchAll();

function sync_conflict_label(string $value): string
{
    return ucfirst(str_replace('_', ' ', $value));
}

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div>
    <p class="eyebrow">ADMINISTRATION</p>
    <h1>Timetable conflict management</h1>
    <p class="muted">Review reported clashes and record the decision for each case.</p>
  </div>
  <div class="date-chip"><span class="calendar-icon">⚑</span><span><?= $counts['open'] + $counts['reviewing'] ?> require attention</span></div>
</div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>

<section class="conflict-stats" aria-label="Conflict summary">
  <a class="conflict-stat <?= $filter === 'all' ? 'selected' : '' ?>" href="conflicts.php?status=all">
    <span>All reports</span><strong><?= $totalConflicts ?></strong>
  </a>
  <a class="conflict-stat <?= $filter === 'open' ? 'selected' : '' ?>" href="conflicts.php?status=open">
    <span>Open</span><strong><?= $counts['open'] ?></strong>
  </a>
  <a class="conflict-stat <?= $filter === 'reviewing' ? 'selected' : '' ?>" href="conflicts.php?status=reviewing">
    <span>Reviewing</span><strong><?= $counts['reviewing'] ?></strong>
  </a>
  <a class="conflict-stat <?= $filter === 'unavoidable' ? 'selected' : '' ?>" href="conflicts.php?status=unavoidable">
    <span>Unavoidable</span><strong><?= $counts['unavoidable'] ?></strong>
  </a>
  <a class="conflict-stat <?= $filter === 'resolved' ? 'selected' : '' ?>" href="conflicts.php?status=resolved">
    <span>Resolved</span><strong><?= $counts['resolved'] ?></strong>
  </a>
</section>

<section class="panel conflict-list-panel">
  <div class="panel-heading">
    <div>
      <h2><?= $filter === 'all' ? 'All reported conflicts' : e(sync_conflict_label($filter)) . ' conflicts' ?></h2>
      <p class="muted">A rejected scheduling attempt is recorded here. It does not create a class on the timetable.</p>
    </div>
    <span class="demo-pill"><?= count($conflicts) ?> RECORDS</span>
  </div>

  <?php if (!$conflicts): ?>
    <div class="empty-state">No conflict reports match this filter.</div>
  <?php else: ?>
    <div class="conflict-list">
      <?php foreach ($conflicts as $conflict): ?>
        <article class="conflict-card">
          <div class="conflict-card-top">
            <div>
              <div class="conflict-meta">
                <span class="conflict-id">CASE #<?= (int) $conflict['id'] ?></span>
                <span class="status-pill <?= e('status-' . $conflict['status']) ?>"><?= e(sync_conflict_label($conflict['status'])) ?></span>
                <span class="type-pill"><?= e(sync_conflict_label($conflict['conflict_type'])) ?></span>
              </div>
              <h3><?= e($conflict['course_code'] ? $conflict['course_code'] . ' · ' . $conflict['course_title'] : 'Scheduling conflict report') ?></h3>
            </div>
            <time><?= e(date('M j, Y · g:i A', strtotime($conflict['created_at']))) ?></time>
          </div>

          <?php if ($conflict['day_of_week']): ?>
            <div class="conflict-schedule">
              <span><?= e($conflict['day_of_week']) ?></span>
              <span><?= e(date('g:i A', strtotime($conflict['start_time'])) . ' to ' . date('g:i A', strtotime($conflict['end_time']))) ?></span>
              <span><?= e($conflict['venue']) ?></span>
              <span><?= e($conflict['class_section']) ?></span>
            </div>
          <?php endif; ?>

          <p class="conflict-description"><?= nl2br(e($conflict['description'])) ?></p>
          <p class="conflict-reporter">Reported by <?= e($conflict['reporter_name'] ?? 'A user whose account is no longer available') ?></p>

          <?php if ($conflict['resolution_note']): ?>
            <div class="conflict-note">
              <strong>Administrator note</strong>
              <p><?= nl2br(e($conflict['resolution_note'])) ?></p>
              <?php if ($conflict['resolver_name']): ?><small>Last decision by <?= e($conflict['resolver_name']) ?><?= $conflict['resolved_at'] ? ' · ' . e(date('M j, Y g:i A', strtotime($conflict['resolved_at']))) : '' ?></small><?php endif; ?>
            </div>
          <?php endif; ?>

          <form method="post" action="conflicts.php<?= $filter !== 'all' ? '?status=' . rawurlencode($filter) : '' ?>" class="conflict-review-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="conflict_id" value="<?= (int) $conflict['id'] ?>">
            <div class="conflict-review-fields">
              <div class="field-group">
                <label for="status-<?= (int) $conflict['id'] ?>">Update status</label>
                <select id="status-<?= (int) $conflict['id'] ?>" name="status" required>
                  <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $conflict['status'] === $status ? 'selected' : '' ?>><?= e(sync_conflict_label($status)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field-group conflict-note-field">
                <label for="note-<?= (int) $conflict['id'] ?>">Decision or review note</label>
                <textarea id="note-<?= (int) $conflict['id'] ?>" name="resolution_note" rows="2" maxlength="3000" placeholder="Explain what you checked or how the conflict was handled."><?= e($conflict['resolution_note'] ?? '') ?></textarea>
              </div>
              <button class="primary-button conflict-save" type="submit">Save review</button>
            </div>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
