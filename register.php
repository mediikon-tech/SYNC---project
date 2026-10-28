<?php
$pageTitle = 'Student registration';
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
        <form class="auth-form" action="#" method="post" onsubmit="event.preventDefault(); document.getElementById('demoMessage').hidden=false;">
          <div class="form-grid">
            <div class="field-group"><label for="full_name">Full name</label><input id="full_name" name="full_name" type="text" placeholder="Your full name" autocomplete="name" required></div>
            <div class="field-group"><label for="matric_number">Matric number</label><input id="matric_number" name="matric_number" type="text" placeholder="e.g. CSC/22/0001" required></div>
            <div class="field-group field-full"><label for="email">Email address</label><input id="email" name="email" type="email" placeholder="you@university.edu" autocomplete="email" required></div>
            <div class="field-group"><label for="department">Department</label><select id="department" name="department" required><option value="">Select department</option><option>Computer Science</option><option>Information Technology</option><option>Mathematics</option><option>Statistics</option><option>Other</option></select></div>
            <div class="field-group"><label for="level">Academic level</label><select id="level" name="level" required><option value="">Select level</option><option value="100">100 Level</option><option value="200">200 Level</option><option value="300">300 Level</option><option value="400">400 Level</option><option value="500">500 Level</option></select></div>
            <div class="field-group field-full"><label for="password">Create password</label><input id="password" name="password" type="password" placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required></div>
          </div>
          <label class="remember-row"><input type="checkbox" required> <span>I confirm that the student information provided is accurate.</span></label>
          <button class="primary-button" type="submit">Create student account <span>→</span></button>
          <p class="form-feedback" id="demoMessage" hidden>This is the interface prototype. Registration and course allocation will be connected to the database in the next development step.</p>
        </form>
        <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
        <a href="index.php" class="back-link">← Back to dashboard preview</a>
      </div>
    </section>
  </main>
</body>
</html>
