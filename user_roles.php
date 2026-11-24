<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$admin = current_user();
if (($admin['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrator permission is required.');
}

require_once __DIR__ . '/config/database.php';

$pageTitle = 'Manage user roles';
$activePage = 'user_roles';
$error = '';
$success = '';
$targetUser = null;
$departments = $pdo->query('SELECT id, name, code FROM departments ORDER BY name')->fetchAll();
$allowedRoles = ['lecturer', 'course_rep', 'admin'];
$email = strtolower(trim((string) ($_POST['lookup_email'] ?? $_POST['email'] ?? $_GET['email'] ?? '')));

$findUser = static function (PDO $pdo, string $email): ?array {
    $query = $pdo->prepare(
        'SELECT u.id, u.full_name, u.email, u.matric_number, u.role, u.department_id, u.academic_level, u.is_active, d.name AS department_name
         FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.email = ? LIMIT 1'
    );
    $query->execute([$email]);
    $user = $query->fetch();
    return $user ?: null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $targetUser = $findUser($pdo, $email);
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $newEmail = strtolower(trim((string)($_POST['new_email'] ?? '')));
    $matric = trim((string)($_POST['matric_number'] ?? ''));
    $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
    $level = filter_var($_POST['academic_level'] ?? null, FILTER_VALIDATE_INT);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$targetUser) {
        $error = 'The account could not be found. Search for it again.';
    } elseif ((int)$targetUser['id'] === (int)$admin['id'] && $isActive === 0) {
        $error = 'You cannot deactivate your own administrator account.';
    } elseif ($fullName === '' || strlen($fullName) > 160 || !filter_var($newEmail, FILTER_VALIDATE_EMAIL) ||
        strlen($newEmail) > 190 || strlen($matric) > 60) {
        $error = 'Enter a valid name, email address, and matric number.';
    } elseif ($newPassword !== '' && (strlen($newPassword) < 8 || $newPassword !== $confirmPassword)) {
        $error = 'A new password must contain at least 8 characters and match the confirmation.';
    } elseif ($targetUser['role'] === 'student' && (!$departmentId || !in_array($level,[100,200,300,400,500,600],true))) {
        $error = 'Student accounts require a department and academic level.';
    } elseif (in_array($targetUser['role'],['lecturer','course_rep'],true) && !$departmentId) {
        $error = 'Lecturer and course representative accounts require a department.';
    } else {
        try {
            if ($departmentId) {
                $departmentCheck=$pdo->prepare('SELECT id FROM departments WHERE id=?');
                $departmentCheck->execute([$departmentId]);
                if (!$departmentCheck->fetchColumn()) throw new RuntimeException('Choose a valid department.');
            }
            if ($targetUser['role'] === 'admin') {
                $departmentId = null;
                $level = null;
                if ($isActive === 0) {
                    $activeAdmins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
                    if ($activeAdmins <= 1) throw new RuntimeException('At least one active administrator must remain.');
                }
            } elseif ($targetUser['role'] !== 'student') {
                $level = null;
            }

            $pdo->beginTransaction();
            if ($targetUser['role'] === 'course_rep' && (int)$targetUser['department_id'] !== (int)$departmentId) {
                $repPosition=$pdo->prepare('SELECT position FROM department_representatives WHERE representative_user_id=? FOR UPDATE');
                $repPosition->execute([(int)$targetUser['id']]);
                $position=$repPosition->fetchColumn();
                if ($position !== false) {
                    $occupied=$pdo->prepare('SELECT representative_user_id FROM department_representatives WHERE department_id=? AND position=? FOR UPDATE');
                    $occupied->execute([$departmentId,$position]);
                    $other=$occupied->fetchColumn();
                    if ($other !== false && (int)$other !== (int)$targetUser['id']) {
                        throw new RuntimeException('That representative position is already occupied in the selected department.');
                    }
                    $moveRep=$pdo->prepare('UPDATE department_representatives SET department_id=? WHERE representative_user_id=?');
                    $moveRep->execute([$departmentId,(int)$targetUser['id']]);
                }
            }
            if ($newPassword !== '') {
                $update=$pdo->prepare('UPDATE users SET full_name=?,email=?,matric_number=?,department_id=?,academic_level=?,is_active=?,password_hash=? WHERE id=?');
                $update->execute([$fullName,$newEmail,$matric !== '' ? $matric : null,$departmentId ?: null,$level ?: null,$isActive,password_hash($newPassword,PASSWORD_DEFAULT),(int)$targetUser['id']]);
            } else {
                $update=$pdo->prepare('UPDATE users SET full_name=?,email=?,matric_number=?,department_id=?,academic_level=?,is_active=? WHERE id=?');
                $update->execute([$fullName,$newEmail,$matric !== '' ? $matric : null,$departmentId ?: null,$level ?: null,$isActive,(int)$targetUser['id']]);
            }
            $pdo->commit();
            $email=$newEmail;
            $success=$newPassword !== '' ? 'User account details and password updated.' : 'User account details updated.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($exception instanceof RuntimeException) $error=$exception->getMessage();
            elseif ($exception instanceof PDOException && $exception->getCode()==='23000') $error='That email address or matric number is already in use.';
            else {
                error_log('Sync admin user profile update failed: '.$exception->getMessage());
                $error='The user account could not be updated.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_role') {
    $newRole = (string) ($_POST['role'] ?? '');
    $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
    $position = filter_var($_POST['position'] ?? null, FILTER_VALIDATE_INT);

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'The account email is invalid. Search for the account again.';
    } elseif (!in_array($newRole, $allowedRoles, true)) {
        $error = 'Choose a valid role to assign.';
    } elseif (in_array($newRole, ['lecturer', 'course_rep'], true) && !$departmentId) {
        $error = 'Choose the department for this staff account.';
    } elseif ($newRole === 'course_rep' && !in_array($position, [1, 2], true)) {
        $error = 'Choose representative position 1 or 2.';
    } else {
        $targetUser = $findUser($pdo, $email);
        if (!$targetUser) {
            $error = 'No registered account matches that email address. The user must register as a student first.';
        } elseif ((int) $targetUser['id'] === (int) $admin['id']) {
            $error = 'You cannot change your own administrator role.';
        } else {
            try {
                if (in_array($newRole, ['lecturer', 'course_rep'], true)) {
                    $departmentCheck = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
                    $departmentCheck->execute([$departmentId]);
                    if (!$departmentCheck->fetch()) {
                        throw new RuntimeException('Choose a valid department.');
                    }
                }

                $pdo->beginTransaction();

                if ($newRole === 'admin') {
                    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                    if ($adminCount < 1) {
                        throw new RuntimeException('At least one active administrator must remain.');
                    }
                    $newDepartmentId = null;
                } else {
                    $newDepartmentId = $departmentId ?: null;
                }

                if ($newRole === 'course_rep') {
                    $positionCheck = $pdo->prepare(
                        'SELECT representative_user_id FROM department_representatives
                         WHERE department_id = ? AND position = ? FOR UPDATE'
                    );
                    $positionCheck->execute([$departmentId, $position]);
                    $occupiedBy = $positionCheck->fetchColumn();
                    if ($occupiedBy !== false && (int) $occupiedBy !== (int) $targetUser['id']) {
                        throw new RuntimeException('That representative position is already assigned. Choose the other position.');
                    }
                }

                $removeOldRep = $pdo->prepare('DELETE FROM department_representatives WHERE representative_user_id = ?');
                $removeOldRep->execute([(int) $targetUser['id']]);

                $update = $pdo->prepare(
                    'UPDATE users SET role = ?, department_id = ?, academic_level = NULL WHERE id = ?'
                );
                $update->execute([$newRole, $newDepartmentId, (int) $targetUser['id']]);

                if ($newRole === 'course_rep') {
                    $assignRep = $pdo->prepare(
                        'INSERT INTO department_representatives (department_id, representative_user_id, position)
                         VALUES (?, ?, ?)'
                    );
                    $assignRep->execute([$departmentId, (int) $targetUser['id'], $position]);
                }

                $pdo->commit();
                $success = $targetUser['full_name'] . ' now has the ' . str_replace('_', ' ', $newRole) . ' role.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception instanceof RuntimeException) {
                    $error = $exception->getMessage();
                } elseif ($exception instanceof PDOException && $exception->getCode() === '23000') {
                    $error = 'That representative position is already occupied. Refresh and choose an available position.';
                } else {
                    error_log('Sync role assignment failed: ' . $exception->getMessage());
                    $error = 'The role could not be updated. Please try again.';
                }
            }
        }
    }
}

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $targetUser = $findUser($pdo, $email);
    if (!$targetUser && $error === '') {
        $error = 'No registered account matches that email address.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
  <div>
    <p class="eyebrow">ADMINISTRATION</p>
    <h1>Manage user roles</h1>
    <p class="muted">Find a registered account by email, review its details, then assign its staff role.</p>
  </div>
</div>

<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>

<section class="panel schedule-form-panel">
  <div class="panel-heading"><div><h2>Find a registered user</h2><p class="muted">Every public signup starts as a student. Only an administrator can assign staff access.</p></div></div>
  <form method="get" action="user_roles.php" class="schedule-form">
    <div class="form-grid">
      <div class="field-group field-full">
        <label for="email">Registered email address</label>
        <input id="email" name="email" type="email" maxlength="190" value="<?= e($email) ?>" placeholder="student@example.com" required>
      </div>
    </div>
    <button class="primary-button" type="submit">Search account <span>→</span></button>
  </form>
</section>

<?php if ($targetUser !== null): ?>
<section class="panel class-list-panel">
  <div class="panel-heading"><div><h2>Account details</h2><p class="muted">Review this account before changing its permissions.</p></div></div>
  <div class="table-scroll">
    <table class="data-table"><tbody>
      <tr><th>Name</th><td><?= e($targetUser['full_name']) ?></td></tr>
      <tr><th>Email</th><td><?= e($targetUser['email']) ?></td></tr>
      <tr><th>Current role</th><td><?= e(ucwords(str_replace('_', ' ', $targetUser['role']))) ?></td></tr>
      <tr><th>Department</th><td><?= e($targetUser['department_name'] ?? 'Not assigned') ?></td></tr>
    </tbody></table>
  </div>
  <form method="post" action="user_roles.php" class="schedule-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="update_profile">
    <input type="hidden" name="lookup_email" value="<?= e($targetUser['email']) ?>">
    <div class="form-grid">
      <div class="field-group"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="160" value="<?=e($targetUser['full_name'])?>" required></div>
      <div class="field-group"><label for="new_email">Email address</label><input id="new_email" name="new_email" type="email" maxlength="190" value="<?=e($targetUser['email'])?>" required></div>
      <div class="field-group"><label for="matric_number">Matric number</label><input id="matric_number" name="matric_number" maxlength="60" value="<?=e($targetUser['matric_number'] ?? '')?>"></div>
      <div class="field-group"><label for="profile_department">Department</label><select id="profile_department" name="department_id"><option value="">Not assigned</option><?php foreach($departments as $department):?><option value="<?=(int)$department['id']?>" <?=((int)($targetUser['department_id']??0)===(int)$department['id'])?'selected':''?>><?=e($department['name'])?></option><?php endforeach;?></select></div>
      <div class="field-group"><label for="academic_level">Academic level</label><select id="academic_level" name="academic_level"><option value="">Not applicable</option><?php foreach([100,200,300,400,500] as $level):?><option value="<?=$level?>" <?=((int)($targetUser['academic_level']??0)===$level)?'selected':''?>><?=$level?> Level</option><?php endforeach;?></select></div>
      <div class="field-group"><label for="new_password">Set new password (optional)</label><input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current password"></div>
      <div class="field-group"><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" placeholder="Repeat new password"></div>
    </div>
    <label class="remember-row"><input type="checkbox" name="is_active" <?=$targetUser['is_active']?'checked':''?>> <span>Account active</span></label>
    <button class="primary-button" type="submit">Save account details</button>
  </form>
  <?php if ((int) $targetUser['id'] !== (int) $admin['id']): ?>
  <form method="post" action="user_roles.php" class="schedule-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="assign_role">
    <input type="hidden" name="email" value="<?= e($targetUser['email']) ?>">
    <div class="form-grid">
      <div class="field-group">
        <label for="role">Role to assign</label>
        <select id="role" name="role" required>
          <option value="">Choose role</option>
          <option value="lecturer">Lecturer</option>
          <option value="course_rep">Course representative</option>
          <option value="admin">Administrator</option>
        </select>
      </div>
      <div class="field-group" data-role-department hidden>
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
          <option value="">Choose department</option>
          <?php foreach ($departments as $department): ?>
            <option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?> (<?= e($department['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-group" data-rep-position hidden>
        <label for="position">Representative position</label>
        <select id="position" name="position">
          <option value="">Choose position</option>
          <option value="1">Position 1</option>
          <option value="2">Position 2</option>
        </select>
      </div>
    </div>
    <button class="primary-button" type="submit">Assign role <span>→</span></button>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel">
  <p class="muted">Course representatives occupy one of two positions in their department. The system rejects an assignment if both positions are already filled. Lecturer and representative accounts must have a department. Public registration cannot create staff or administrator accounts.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
