<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();
$currentUser = current_user();
$pageTitle = 'Dashboard';
$today = date('l, j F Y');
$courses = [
    ['code' => 'CSC 401', 'title' => 'Software Engineering', 'time' => '09:00 AM - 11:00 AM', 'venue' => 'LT 2', 'lecturer' => 'Dr. A. Musa', 'day' => 'Today'],
    ['code' => 'CSC 405', 'title' => 'Database Systems', 'time' => '12:00 PM - 02:00 PM', 'venue' => 'Lab 1', 'lecturer' => 'Mr. J. Okafor', 'day' => 'Today'],
    ['code' => 'CSC 409', 'title' => 'Computer Networks', 'time' => '10:00 AM - 12:00 PM', 'venue' => 'LT 1', 'lecturer' => 'Mrs. S. Bello', 'day' => 'Tomorrow'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> | Sync</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php"><span class="brand-mark">S</span><span>sync<span class="brand-dot">.</span><small>SMART TIMETABLE</small></span></a>
        <div class="nav-label">WORKSPACE</div>
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-link active" href="index.php"><span class="nav-icon">▦</span> Dashboard</a>
            <a class="nav-link" href="timetable.php"><span class="nav-icon">▤</span> Weekly timetable</a>
            <a class="nav-link" href="courses.php"><span class="nav-icon">▣</span> My courses</a>
            <a class="nav-link" href="timetable.php"><span class="nav-icon">＋</span> Schedule a class</a>
        </nav>
        <div class="nav-label management-label">MANAGEMENT</div>
        <nav class="main-nav" aria-label="Management navigation">
            <a class="nav-link" href="register.php"><span class="nav-icon">♙</span> Student registration</a>
            <a class="nav-link" href="login.php"><span class="nav-icon">↪</span> Sign in</a>
            <a class="nav-link" href="register.php"><span class="nav-icon">＋</span> Create account</a>
        </nav>
        <div class="sidebar-bottom">
            <div class="help-card"><div class="help-symbol">?</div><strong>Need a hand?</strong><p>Get help using Sync and managing your timetable.</p><a href="mailto:support@sync.local">Contact support ↗</a></div>
            <div class="user-mini"><div class="avatar"><?= e(strtoupper(substr((string) ($currentUser['full_name'] ?? 'S'), 0, 1))) ?></div><div><strong><?= e($currentUser['full_name'] ?? 'Student') ?></strong><small><?= e($currentUser['department_name'] ?? ucfirst($currentUser['role'] ?? 'user')) ?></small></div><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="user-menu" type="submit" aria-label="Sign out" title="Sign out">↪</button></form></div>
        </div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Toggle navigation">☰</button>
            <div class="breadcrumb">Workspace <span>/</span> <strong>Dashboard</strong></div>
            <div class="topbar-actions"><span class="term-pill"><span></span> 2026/2027 Session</span><button class="icon-button" aria-label="Notifications">♧<i></i></button><div class="avatar top-avatar">MV</div></div>
        </header>
        <section class="page-wrap">
            <div class="welcome-row"><div><p class="eyebrow">STUDENT WORKSPACE</p><h1>Good day, Student <span>✦</span></h1><p class="muted">Here is your academic schedule at a glance.</p></div><div class="date-chip"><span class="calendar-icon">▦</span><span><?= htmlspecialchars($today) ?></span></div></div>
            <section class="stats-grid" aria-label="Academic summary">
                <article class="stat-card"><div class="stat-top"><span>Registered courses</span><span class="stat-icon green-icon">▣</span></div><div class="stat-number">06</div><div class="stat-foot"><span class="status-dot"></span> Your current course list</div></article>
                <article class="stat-card"><div class="stat-top"><span>Classes this week</span><span class="stat-icon blue-icon">▤</span></div><div class="stat-number">12</div><div class="stat-foot">Across your registered courses</div></article>
                <article class="stat-card"><div class="stat-top"><span>Next class</span><span class="stat-icon purple-icon">◷</span></div><div class="stat-number stat-time">09:00 <small>AM</small></div><div class="stat-foot">Software Engineering</div></article>
                <article class="stat-card"><div class="stat-top"><span>Timetable status</span><span class="stat-icon amber-icon">✓</span></div><div class="stat-number status-number">Ready</div><div class="stat-foot"><span class="status-dot"></span> Review your weekly plan</div></article>
            </section>
            <section class="content-grid">
                <article class="panel timetable-panel" id="timetable">
                    <div class="panel-heading"><div><h2>Your timetable</h2><p class="muted">Keep track of your upcoming classes.</p></div><a class="text-link" href="timetable.php">View full timetable <span>↗</span></a></div>
                    <div class="day-switcher"><button class="day-tab selected" data-day="Today">Today <span>3</span></button><button class="day-tab" data-day="Tomorrow">Tomorrow <span>2</span></button><button class="day-tab" data-day="Friday">Friday <span>4</span></button></div>
                    <div class="class-list" id="classList">
                        <?php foreach ($courses as $course): ?>
                        <div class="class-row" data-day="<?= htmlspecialchars($course['day']) ?>">
                            <div class="class-time"><?= htmlspecialchars($course['time']) ?></div><div class="class-line"><span></span></div>
                            <div class="class-details"><div class="class-title-row"><div><span class="course-code"><?= htmlspecialchars($course['code']) ?></span><h3><?= htmlspecialchars($course['title']) ?></h3></div><span class="class-tag"><?= htmlspecialchars($course['day']) ?></span></div><div class="class-meta"><span>♙ <?= htmlspecialchars($course['lecturer']) ?></span><span>⌂ <?= htmlspecialchars($course['venue']) ?></span></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="empty-state" id="emptyState" hidden>No sample classes for this day yet.</div>
                </article>
                <aside class="right-column">
                    <article class="panel quick-panel"><div class="panel-heading"><div><h2>Quick actions</h2><p class="muted">Jump into your workflow.</p></div></div><a class="quick-action" href="courses.php"><span class="quick-icon green-icon">▣</span><span><strong>Manage courses</strong><small>Add or remove your courses</small></span><b>›</b></a><a class="quick-action" href="timetable.php"><span class="quick-icon blue-icon">＋</span><span><strong>Schedule a class</strong><small>Submit a new class schedule</small></span><b>›</b></a><a class="quick-action" href="#weekly-view"><span class="quick-icon purple-icon">▤</span><span><strong>Generate timetable</strong><small>Build your weekly view</small></span><b>›</b></a></article>
                    <article class="tip-card"><div class="tip-icon">✦</div><div><strong>Keep your timetable updated</strong><p>Check course and venue details before heading to class. Sync will flag schedule conflicts as scheduling features are added.</p></div></article>
                </aside>
            </section>
            <section class="panel course-panel" id="courses"><div class="panel-heading"><div><h2>Your courses</h2><p class="muted">A quick look at your registered courses.</p></div><a class="text-link" href="courses.php">Manage courses <span>↗</span></a></div><div class="course-chips"><span><b>CSC 401</b> Software Engineering</span><span><b>CSC 405</b> Database Systems</span><span><b>CSC 409</b> Computer Networks</span><span><b>CSC 411</b> Artificial Intelligence</span><span><b>CSC 415</b> Research Methods</span><span><b>CSC 417</b> Project Management</span></div></section>
            <footer class="footer"><span>© <?= date('Y') ?> Sync. Smart course allocation and timetable management.</span><span>Built for a clearer academic week.</span></footer>
        </section>
    </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
