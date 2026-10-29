<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Student registration';
$error = '';
$departments = [];
$old = ['full_name' => '', 'matric_number' => '', 'email' => '', 'department_id' => '', 'level' => ''];

try {
    require_once __DIR__ . '/config/database.php';
    $departments = $pdo->query('SELECT id, name, code FROM departments ORDER BY name')->fetchAll();
} catch (Throwable $exception) {
    $error = 'The database is not ready yet. Import database/schema.sql and check config/database.php.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['full_name', 'matric_number', 'email', 'department_id', 'level'] as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Please refresh the page and try again.';
    } elseif (!isset($pdo)) {
        $error = 'The database connection is unavailable. Check your XAMPP MySQL setup.';
    } elseif ($old['full_name'] === '' || $old['matric_number'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter your full name, matric number, and a valid email address.';
    } elseif (!ctype_digit($old['department_id']) || !in_array((int) $old['level'], [100, 200, 300, 400, 500], true)) {
        $error = 'Choose a valid department and academic level.';
    } elseif (!isset($_POST['confirm_details'])) {
        $error = 'Please confirm that your student information is accurate.';
    } elseif (strlen((string) ($_POST['password'] ?? '')) < 8) {
        $error = 'Your password must contain at least 8 characters.';
    } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $departmentCheck = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
            $departmentCheck->execute([(int) $old['department_id']]);
            if (!$departmentCheck->fetch()) {
                $error = 'That department is not available. Refresh the page and choose another.';
            } else {
                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    'INSERT INTO users (full_name, matric_number, email, password_hash, role, department_id, academic_level)
                     VALUES (:full_name, :matric_number, :email, :password_hash, \'student\', :department_id, :academic_level)'
                );
                $insert->execute([
                    'full_name' => $old['full_name'],
                    'matric_number' => $old['matric_number'],
                    'email' => strtolower($old['email']),
                    'password_hash' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                    'department_id' => (int) $old['department_id'],
                    'academic_level' => (int) $old['level'],
                ]);
                $studentId = (int) $pdo->lastInsertId();

                // Allocate currently configured defaults. Students can later remove or restore them.
                $defaults = $pdo->prepare(
                    "INSERT IGNORE INTO student_courses (student_id, course_id, academic_session, semester, selection_source)
                     SELECT ?, course_id, academic_session, semester, 'default'
                     FROM department_course_levels
                     WHERE department_id = ? AND academic_level = ? AND is_default = 1
                       AND academic_session = '2026/2027'"
                );
                $defaults->execute([$studentId, (int) $old['department_id'], (int) $old['level']]);
                $pdo->commit();

                session_regenerate_id(true);
                $userQuery = $pdo->prepare(
                    'SELECT u.id, u.full_name, u.email, u.role, u.department_id, u.academic_level, d.name AS department_name
                     FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ?'
                );
                $userQuery->execute([$studentId]);
                $_SESSION['user'] = $userQuery->fetch();
                unset($_SESSION['csrf_token']);
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                $error = 'That email address or matric number is already registered.';
            } else {
                error_log('Sync registration failed: ' . $exception->getMessage());
                $error = 'Registration could not be completed. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student registration | Sync</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
  <main class="auth-shell register-shell">
    <section class="auth-brand-panel">
      <a class="brand auth-brand" href="index.php"><span class="brand-mark">S</span><span>sync<span class="brand-dot">.</span><small>SMART TIMETABLE</small></span></a>
      <div class="auth-message"><span class="eyebrow">GET STARTED</span><h1>Your academic week, organised.</h1><p>Create your student profile to keep your courses and class schedule together.</p><div class="auth-feature"><span>✓</span> Choose your department and level</div><div class="auth-feature"><span>✓</span> Review your allocated courses</div><div class="auth-feature"><span>✓</span> View your weekly class timetable</div></div>
      <div class="auth-brand-footer">MADE FOR A MORE ORGANISED CAMPUS</div>
    </section>
    <section class="auth-form-panel">
      <div class="auth-form-wrap register-form-wrap">
        <div class="mobile-auth-brand"><a class="brand" href="index.php"><span class="brand-mark">S</span><span>sync<span class="brand-dot">.</span><small>SMART TIMETABLE</small></span></a></div>
        <span class="eyebrow">STUDENT ACCOUNT</span><h2>Create your account</h2><p class="muted auth-subtitle">Fill in your details to set up your student profile.</p>
        <?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form class="auth-form" action="register.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="form-grid">
            <div class="field-group"><label for="full_name">Full name</label><input id="full_name" name="full_name" type="text" value="<?= e($old['full_name']) ?>" placeholder="Your full name" autocomplete="name" maxlength="160" required></div>
            <div class="field-group"><label for="matric_number">Matric number</label><input id="matric_number" name="matric_number" type="text" value="<?= e($old['matric_number']) ?>" placeholder="e.g. CSC/22/0001" maxlength="60" required></div>
            <div class="field-group field-full"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($old['email']) ?>" placeholder="you@university.edu" autocomplete="email" maxlength="190" required></div>
            <div class="field-group"><label for="department_id">Department</label><select id="department_id" name="department_id" required><option value="">Select department</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= $old['department_id'] === (string) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field-group"><label for="level">Academic level</label><select id="level" name="level" required><option value="">Select level</option><?php foreach ([100,200,300,400,500] as $level): ?><option value="<?= $level ?>" <?= $old['level'] === (string) $level ? 'selected' : '' ?>><?= $level ?> Level</option><?php endforeach; ?></select></div>
            <div class="field-group"><label for="password">Create password</label><input id="password" name="password" type="password" placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required></div>
            <div class="field-group"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" placeholder="Enter it again" minlength="8" autocomplete="new-password" required></div>
          </div>
          <label class="remember-row"><input type="checkbox" name="confirm_details" value="1" required> <span>I confirm that the student information provided is accurate.</span></label>
          <button class="primary-button" type="submit">Create student account <span>→</span></button>
        </form>
        <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
        <a href="index.php" class="back-link">← Back to dashboard</a>
      </div>
    </section>
  </main>
</body>
</html>
