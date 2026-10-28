<?php
if (!isset($pageTitle)) $pageTitle = 'Workspace';
if (!isset($activePage)) $activePage = '';
$navItems = [
    'dashboard' => ['index.php', '▦', 'Dashboard'],
    'timetable' => ['timetable.php', '▤', 'Weekly timetable'],
    'courses' => ['courses.php', '▣', 'My courses'],
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
      <?php foreach ($navItems as $key => $item): ?>
      <a class="nav-link <?= $activePage === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($item[0]) ?>"><span class="nav-icon"><?= $item[1] ?></span><?= htmlspecialchars($item[2]) ?></a>
      <?php endforeach; ?>
      <a class="nav-link" href="courses.php#course-list"><span class="nav-icon">＋</span> Add or remove courses</a>
    </nav>
    <div class="nav-label management-label">ACCOUNT</div>
    <nav class="main-nav" aria-label="Account navigation">
      <a class="nav-link" href="register.php"><span class="nav-icon">♙</span> Student registration</a>
      <a class="nav-link" href="login.php"><span class="nav-icon">↪</span> Sign in</a>
    </nav>
    <div class="sidebar-bottom">
      <div class="help-card"><div class="help-symbol">?</div><strong>Need a hand?</strong><p>Keep your course details current and check your timetable before class.</p><a href="mailto:support@sync.local">Contact support ↗</a></div>
      <div class="user-mini"><div class="avatar">SU</div><div><strong>Student User</strong><small>Computer Science</small></div><span class="user-menu">•••</span></div>
    </div>
  </aside>
  <main class="main-content">
    <header class="topbar">
      <button class="mobile-menu" id="mobileMenu" aria-label="Toggle navigation">☰</button>
      <div class="breadcrumb">Workspace <span>/</span> <strong><?= htmlspecialchars($pageTitle) ?></strong></div>
      <div class="topbar-actions"><span class="term-pill"><span></span> 2026/2027 Session</span><a class="avatar top-avatar" href="login.php" aria-label="Sign in">SU</a></div>
    </header>
    <section class="page-wrap">
