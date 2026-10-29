<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Sign in';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            require_once __DIR__ . '/config/database.php';
            $query = $pdo->prepare(
                'SELECT u.id, u.full_name, u.email, u.password_hash, u.role, u.department_id,
                        u.academic_level, u.is_active, d.name AS department_name
                 FROM users u LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.email = ? LIMIT 1'
            );
            $query->execute([$email]);
            $user = $query->fetch();

            if (!$user || !password_verify($password, $user['password_hash']) || (int) $user['is_active'] !== 1) {
                $error = 'The email or password is incorrect, or the account is inactive.';
            } else {
                session_regenerate_id(true);
                unset($user['password_hash'], $user['is_active']);
                $_SESSION['user'] = $user;
                unset($_SESSION['csrf_token']);
                header('Location: index.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Sync sign-in failed: ' . $exception->getMessage());
            $error = 'Sign-in is temporarily unavailable. Check that MySQL is running and the schema is imported.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | Sync</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
  <main class="auth-shell">
    <section class="auth-brand-panel">
      <a class="brand auth-brand" href="index.php"><span class="brand-mark">S</span><span>sync<span class="brand-dot">.</span><small>SMART TIMETABLE</small></span></a>
      <div class="auth-message"><span class="eyebrow">YOUR ACADEMIC WORKSPACE</span><h1>Make every class count.</h1><p>Keep your courses organised and your weekly timetable in one clear place.</p><div class="auth-feature"><span>✓</span> One view for your academic week</div><div class="auth-feature"><span>✓</span> Course information in one place</div><div class="auth-feature"><span>✓</span> A clearer way to plan your classes</div></div>
      <div class="auth-brand-footer">SMART COURSE ALLOCATION AND TIMETABLE MANAGEMENT</div>
    </section>
    <section class="auth-form-panel">
      <div class="auth-form-wrap">
        <div class="mobile-auth-brand"><a class="brand" href="index.php"><span class="brand-mark">S</span><span>sync<span class="brand-dot">.</span><small>SMART TIMETABLE</small></span></a></div>
        <span class="eyebrow">WELCOME BACK</span><h2>Sign in to Sync</h2><p class="muted auth-subtitle">Enter your account details to continue.</p>
        <?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form class="auth-form" action="login.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <label for="email">Email address</label><input id="email" name="email" type="email" placeholder="you@university.edu" autocomplete="email" maxlength="190" required>
          <div class="label-row"><label for="password">Password</label><span class="form-link" title="Ask your administrator to reset your password">Forgot password?</span></div>
          <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
          <button class="primary-button" type="submit">Sign in <span>→</span></button>
        </form>
        <p class="auth-switch">New to Sync? <a href="register.php">Create a student account</a></p>
        <a href="index.php" class="back-link">← Back to dashboard</a>
      </div>
    </section>
  </main>
</body>
</html>
