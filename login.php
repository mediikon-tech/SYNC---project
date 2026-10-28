<?php
$pageTitle = 'Sign in';
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
        <form class="auth-form" action="#" method="post" onsubmit="event.preventDefault(); document.getElementById('demoMessage').hidden=false;">
          <label for="email">Email address</label><input id="email" name="email" type="email" placeholder="you@university.edu" autocomplete="email" required>
          <div class="label-row"><label for="password">Password</label><a href="#" class="form-link" onclick="alert('Password recovery will be connected when authentication is implemented.'); return false;">Forgot password?</a></div>
          <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
          <label class="remember-row"><input type="checkbox" name="remember"> <span>Keep me signed in on this device</span></label>
          <button class="primary-button" type="submit">Sign in <span>→</span></button>
          <p class="form-feedback" id="demoMessage" hidden>This is the interface prototype. Account sign-in will work after the database and authentication are connected.</p>
        </form>
        <p class="auth-switch">New to Sync? <a href="register.php">Create a student account</a></p>
        <a href="index.php" class="back-link">← Back to dashboard preview</a>
      </div>
    </section>
  </main>
</body>
</html>
