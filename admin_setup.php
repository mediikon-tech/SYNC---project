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
require_once __DIR__ . '/includes/academic.php';
$term = sync_current_academic_term($pdo);

$pageTitle = 'Academic setup';
$activePage = 'admin_setup';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            if ($action === 'save_term') {
                $sessionValue = trim((string) ($_POST['academic_session'] ?? ''));
                $semesterValue = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT);
                if (!preg_match('/^\d{4}\/\d{4}$/', $sessionValue) || !in_array($semesterValue, [1,2], true)) {
                    throw new RuntimeException('Enter an academic session in YYYY/YYYY format and choose semester 1 or 2.');
                }
                $q = $pdo->prepare('UPDATE academic_settings SET academic_session=?, semester=?, updated_by=? WHERE id=1');
                $q->execute([$sessionValue, $semesterValue, (int) $admin['id']]);
                $term = sync_current_academic_term($pdo);
                $success = 'The active academic session and semester have been updated across SYNC.';
            } elseif ($action === 'save_department') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
                $name = trim((string) ($_POST['name'] ?? ''));
                $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
                if ($name === '' || strlen($name) > 120 || !preg_match('/^[A-Z0-9_-]{2,20}$/', $code)) {
                    throw new RuntimeException('Enter a department name and a code containing 2 to 20 letters, numbers, underscores, or hyphens.');
                }
                if ($id > 0) {
                    $q = $pdo->prepare('UPDATE departments SET name = ?, code = ? WHERE id = ?');
                    $q->execute([$name, $code, $id]);
                    $success = 'Department updated.';
                } else {
                    $q = $pdo->prepare('INSERT INTO departments (name, code) VALUES (?, ?)');
                    $q->execute([$name, $code]);
                    $success = 'Department added.';
                }
            } elseif ($action === 'save_course') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
                $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
                $code = strtoupper(trim((string) ($_POST['course_code'] ?? '')));
                $title = trim((string) ($_POST['title'] ?? ''));
                $units = filter_var($_POST['credit_units'] ?? null, FILTER_VALIDATE_INT);
                $type = (string) ($_POST['course_type'] ?? 'core');
                $description = trim((string) ($_POST['description'] ?? ''));
                $active = isset($_POST['is_active']) ? 1 : 0;
                if (!$departmentId || $code === '' || strlen($code) > 30 || $title === '' || strlen($title) > 180 ||
                    !$units || $units < 1 || $units > 12 || !in_array($type, ['core','elective'], true) || strlen($description) > 5000) {
                    throw new RuntimeException('Complete the course code, title, department, valid credit units, and course type.');
                }
                if ($id > 0) {
                    $q = $pdo->prepare('UPDATE courses SET department_id=?, course_code=?, title=?, credit_units=?, course_type=?, description=?, is_active=? WHERE id=?');
                    $q->execute([$departmentId, $code, $title, $units, $type, $description !== '' ? $description : null, $active, $id]);
                    $success = 'Course updated.';
                } else {
                    $q = $pdo->prepare('INSERT INTO courses (department_id, course_code, title, credit_units, course_type, description, is_active) VALUES (?,?,?,?,?,?,?)');
                    $q->execute([$departmentId, $code, $title, $units, $type, $description !== '' ? $description : null, $active]);
                    $success = 'Course added.';
                }
            } elseif ($action === 'save_mapping') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
                $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
                $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
                $level = filter_var($_POST['academic_level'] ?? null, FILTER_VALIDATE_INT);
                $semester = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT);
                $session = trim((string) ($_POST['academic_session'] ?? ''));
                $isDefault = isset($_POST['is_default']) ? 1 : 0;
                if (!$courseId || !$departmentId || !in_array($level, [100,200,300,400,500,600], true) ||
                    !in_array($semester, [1,2], true) || !preg_match('/^\d{4}\/\d{4}$/', $session)) {
                    throw new RuntimeException('Choose a course, department, valid level, semester, and session in YYYY/YYYY format.');
                }
                $courseCheck = $pdo->prepare('SELECT id FROM courses WHERE id=? AND department_id=?');
                $courseCheck->execute([$courseId, $departmentId]);
                if (!$courseCheck->fetchColumn()) {
                    throw new RuntimeException('The selected course must belong to the selected department.');
                }
                if ($id > 0) {
                    $q = $pdo->prepare('UPDATE department_course_levels SET course_id=?, department_id=?, academic_level=?, semester=?, academic_session=?, is_default=? WHERE id=?');
                    $q->execute([$courseId,$departmentId,$level,$semester,$session,$isDefault,$id]);
                    $success = 'Course allocation mapping updated.';
                } else {
                    $q = $pdo->prepare('INSERT INTO department_course_levels (course_id, department_id, academic_level, semester, academic_session, is_default) VALUES (?,?,?,?,?,?)');
                    $q->execute([$courseId,$departmentId,$level,$semester,$session,$isDefault]);
                    $success = 'Course allocation mapping added.';
                }
            } elseif ($action === 'delete_mapping') {
                $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) throw new RuntimeException('Choose a valid course mapping.');
                $q = $pdo->prepare('DELETE FROM department_course_levels WHERE id=?');
                $q->execute([$id]);
                $success = $q->rowCount() ? 'Course mapping removed. Existing student registrations and saved classes were not deleted.' : 'Mapping not found.';
            } elseif ($action === 'save_venue') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
                $name = trim((string) ($_POST['name'] ?? ''));
                $type = trim((string) ($_POST['venue_type'] ?? 'Lecture theatre'));
                $capacityRaw = trim((string) ($_POST['capacity'] ?? ''));
                $capacity = $capacityRaw === '' ? null : filter_var($capacityRaw, FILTER_VALIDATE_INT);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '' || strlen($name) > 120 || $type === '' || strlen($type) > 60 ||
                    ($capacityRaw !== '' && (!$capacity || $capacity < 1 || $capacity > 65535))) {
                    throw new RuntimeException('Enter a venue name, type, and an optional positive capacity.');
                }
                if ($id > 0) {
                    $oldQuery = $pdo->prepare('SELECT name FROM venues WHERE id=?');
                    $oldQuery->execute([$id]);
                    $oldName = $oldQuery->fetchColumn();
                    if ($oldName === false) throw new RuntimeException('Venue not found.');
                    $pdo->beginTransaction();
                    $q = $pdo->prepare('UPDATE venues SET name=?, venue_type=?, capacity=?, is_active=? WHERE id=?');
                    $q->execute([$name,$type,$capacity,$active,$id]);
                    if ((string)$oldName !== $name) {
                        $pdo->prepare('UPDATE class_schedules SET venue=? WHERE venue=?')->execute([$name,$oldName]);
                        $pdo->prepare('UPDATE timetable_availability SET venue=? WHERE venue=?')->execute([$name,$oldName]);
                    }
                    $pdo->commit();
                    $success = 'Venue updated. Existing class schedules and availability periods were updated to use its new name.';
                } else {
                    $q = $pdo->prepare('INSERT INTO venues (name, venue_type, capacity, is_active) VALUES (?,?,?,?)');
                    $q->execute([$name,$type,$capacity,$active]);
                    $success = 'Venue added.';
                }
            } else {
                throw new RuntimeException('Unknown administration action.');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } elseif ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $error = 'A record with that code, name, or course allocation already exists.';
            } else {
                error_log('Sync academic setup failed: ' . $exception->getMessage());
                $error = 'The change could not be saved. Check the details and try again.';
            }
        }
    }
}

$term = sync_current_academic_term($pdo);
$departments = $pdo->query('SELECT id,name,code FROM departments ORDER BY name')->fetchAll();
$courses = $pdo->query('SELECT c.*,d.name AS department_name FROM courses c JOIN departments d ON d.id=c.department_id ORDER BY c.course_code')->fetchAll();
$mappings = $pdo->query('SELECT dcl.*,c.course_code,c.title,d.name AS department_name FROM department_course_levels dcl JOIN courses c ON c.id=dcl.course_id JOIN departments d ON d.id=dcl.department_id ORDER BY d.name,dcl.academic_level,dcl.semester,c.course_code')->fetchAll();
$venues = $pdo->query('SELECT * FROM venues ORDER BY is_active DESC,name')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row"><div><p class="eyebrow">SYSTEM CONFIGURATION</p><h1>Academic setup</h1><p class="muted">Manage departments, courses, course allocation rules, and teaching venues.</p></div></div>
<?php if ($error !== ''): ?><p class="form-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success-feedback" role="status"><?= e($success) ?></p><?php endif; ?>

<section class="panel schedule-form-panel">
 <div class="panel-heading"><div><h2>Active academic term</h2><p class="muted">Changing this setting updates the session and semester used by student course registration, weekly timetables, scheduling, and timetable generation.</p></div></div>
 <form method="post" class="schedule-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_term"><div class="form-grid">
  <div class="field-group"><label>Academic session</label><input name="academic_session" value="<?=e($term['academic_session'])?>" pattern="\d{4}/\d{4}" maxlength="9" placeholder="2026/2027" required></div>
  <div class="field-group"><label>Active semester</label><select name="semester"><option value="1" <?=$term['semester']===1?'selected':''?>>Semester 1</option><option value="2" <?=$term['semester']===2?'selected':''?>>Semester 2</option></select></div>
 </div><button class="primary-button" type="submit">Save active term</button></form>
</section>

<section class="panel schedule-form-panel">
 <div class="panel-heading"><div><h2>Departments</h2><p class="muted">Add departments and update their names or codes.</p></div></div>
 <form method="post" class="schedule-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_department">
  <div class="form-grid"><div class="field-group"><label>Department name</label><input name="name" maxlength="120" required></div><div class="field-group"><label>Department code</label><input name="code" maxlength="20" required placeholder="CSC"></div></div><button class="primary-button" type="submit">Add department</button>
 </form>
 <div class="table-scroll"><table class="data-table"><thead><tr><th>Department</th><th>Code</th><th>Update</th></tr></thead><tbody>
 <?php foreach ($departments as $d): ?><tr><td colspan="3"><form method="post" class="inline-edit-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_department"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input name="name" value="<?= e($d['name']) ?>" maxlength="120" required><input name="code" value="<?= e($d['code']) ?>" maxlength="20" required><button class="small-action add-course">Save</button></form></td></tr><?php endforeach; ?>
 </tbody></table></div>
</section>

<section class="panel schedule-form-panel">
 <div class="panel-heading"><div><h2>Courses</h2><p class="muted">Create and edit course details. Deactivate courses to keep historical records.</p></div></div>
 <form method="post" class="schedule-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_course"><div class="form-grid">
  <div class="field-group"><label>Department</label><select name="department_id" required><option value="">Choose department</option><?php foreach($departments as $d):?><option value="<?= (int)$d['id']?>"><?=e($d['name'])?></option><?php endforeach;?></select></div>
  <div class="field-group"><label>Course code</label><input name="course_code" maxlength="30" required placeholder="CSC401"></div>
  <div class="field-group field-full"><label>Course title</label><input name="title" maxlength="180" required></div>
  <div class="field-group"><label>Credit units</label><input name="credit_units" type="number" min="1" max="12" value="2" required></div>
  <div class="field-group"><label>Course type</label><select name="course_type"><option value="core">Core</option><option value="elective">Elective</option></select></div>
  <div class="field-group field-full"><label>Description</label><textarea name="description" rows="2" maxlength="5000"></textarea></div>
 </div><label class="remember-row"><input type="checkbox" name="is_active" checked> <span>Active and available for allocation</span></label><button class="primary-button">Add course</button></form>
 <div class="table-scroll"><table class="data-table"><thead><tr><th>Course details</th><th>Department</th><th>Units/type</th><th>Status</th><th>Save changes</th></tr></thead><tbody>
 <?php foreach($courses as $c):?><tr><td colspan="5"><form method="post" class="inline-edit-form course-edit-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_course"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><input name="course_code" value="<?=e($c['course_code'])?>" maxlength="30" required><input name="title" value="<?=e($c['title'])?>" maxlength="180" required><textarea name="description" maxlength="5000" placeholder="Course description"><?=e($c['description'] ?? '')?></textarea><select name="department_id"><?php foreach($departments as $d):?><option value="<?=(int)$d['id']?>" <?=((int)$d['id']===(int)$c['department_id'])?'selected':''?>><?=e($d['code'])?></option><?php endforeach;?></select><input name="credit_units" type="number" min="1" max="12" value="<?=(int)$c['credit_units']?>" required><select name="course_type"><option value="core" <?=$c['course_type']==='core'?'selected':''?>>Core</option><option value="elective" <?=$c['course_type']==='elective'?'selected':''?>>Elective</option></select><label><input type="checkbox" name="is_active" <?=$c['is_active']?'checked':''?>> Active</label><button class="small-action add-course">Save</button></form></td></tr><?php endforeach;?>
 </tbody></table></div>
</section>

<section class="panel schedule-form-panel">
 <div class="panel-heading"><div><h2>Default course allocation</h2><p class="muted">Map each course to a department, level, semester, and academic session. Students see only mappings for their own department and level.</p></div></div>
 <form method="post" class="schedule-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_mapping"><div class="form-grid">
  <div class="field-group"><label>Course</label><select name="course_id" required><option value="">Choose course</option><?php foreach($courses as $c):?><option value="<?=(int)$c['id']?>"><?=e($c['course_code'].' · '.$c['title'])?></option><?php endforeach;?></select></div>
  <div class="field-group"><label>Department</label><select name="department_id" required><option value="">Choose department</option><?php foreach($departments as $d):?><option value="<?=(int)$d['id']?>"><?=e($d['name'])?></option><?php endforeach;?></select></div>
  <div class="field-group"><label>Academic level</label><select name="academic_level"><?php foreach([100,200,300,400,500,600] as $l):?><option value="<?=$l?>"><?=$l?> Level</option><?php endforeach;?></select></div>
  <div class="field-group"><label>Semester</label><select name="semester"><option value="1">Semester 1</option><option value="2">Semester 2</option></select></div>
  <div class="field-group"><label>Academic session</label><input name="academic_session" value="<?=e($term['academic_session'])?>" pattern="\d{4}/\d{4}" maxlength="9" required></div>
 </div><label class="remember-row"><input type="checkbox" name="is_default" checked> <span>Default course for this department and level</span></label><button class="primary-button">Add course mapping</button></form>
 <div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Department/level</th><th>Session</th><th>Semester</th><th>Default</th><th>Action</th></tr></thead><tbody>
  <?php foreach($mappings as $m):?><tr><td colspan="6"><form method="post" class="inline-edit-form mapping-edit-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_mapping"><input type="hidden" name="id" value="<?=(int)$m['id']?>"><select name="course_id"><?php foreach($courses as $c):?><option value="<?=(int)$c['id']?>" <?=((int)$c['id']===(int)$m['course_id'])?'selected':''?>><?=e($c['course_code'])?></option><?php endforeach;?></select><select name="department_id"><?php foreach($departments as $d):?><option value="<?=(int)$d['id']?>" <?=((int)$d['id']===(int)$m['department_id'])?'selected':''?>><?=e($d['code'])?></option><?php endforeach;?></select><select name="academic_level"><?php foreach([100,200,300,400,500,600] as $l):?><option value="<?=$l?>" <?=((int)$m['academic_level']===$l)?'selected':''?>><?=$l?></option><?php endforeach;?></select><select name="semester"><option value="1" <?=(int)$m['semester']===1?'selected':''?>>Sem 1</option><option value="2" <?=(int)$m['semester']===2?'selected':''?>>Sem 2</option></select><input name="academic_session" value="<?=e($m['academic_session'])?>" maxlength="9" pattern="\d{4}/\d{4}" required><label><input type="checkbox" name="is_default" <?=$m['is_default']?'checked':''?>> Default</label><button class="small-action add-course" type="submit">Save</button><button class="small-action remove-course" type="submit" name="action" value="delete_mapping" onclick="return confirm('Remove this allocation mapping? Existing student registrations are kept.');">Remove</button></form></td></tr><?php endforeach;?>
 </tbody></table></div>
</section>

<section class="panel schedule-form-panel">
 <div class="panel-heading"><div><h2>Teaching venues</h2><p class="muted">Manage lecture theatres, classrooms, laboratories, and other locations. Renaming a venue updates saved schedules and available periods. Deactivation prevents new selection.</p></div></div>
 <form method="post" class="schedule-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_venue"><div class="form-grid">
  <div class="field-group"><label>Venue name</label><input name="name" maxlength="120" required placeholder="Lecture Theatre 1"></div>
  <div class="field-group"><label>Venue type</label><select name="venue_type"><option>Lecture theatre</option><option>Classroom</option><option>Laboratory</option><option>Auditorium</option><option>Seminar room</option><option>Other</option></select></div>
  <div class="field-group"><label>Capacity (optional)</label><input name="capacity" type="number" min="1" max="65535"></div>
 </div><label class="remember-row"><input type="checkbox" name="is_active" checked> <span>Active for new scheduling</span></label><button class="primary-button">Add venue</button></form>
 <div class="table-scroll"><table class="data-table"><thead><tr><th>Venue</th><th>Type</th><th>Capacity</th><th>Status</th><th>Save changes</th></tr></thead><tbody>
 <?php foreach($venues as $v):?><tr><td colspan="5"><form method="post" class="inline-edit-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_venue"><input type="hidden" name="id" value="<?=(int)$v['id']?>"><input name="name" value="<?=e($v['name'])?>" maxlength="120" required><input name="venue_type" value="<?=e($v['venue_type'])?>" maxlength="60" required><input name="capacity" type="number" min="1" max="65535" value="<?=e((string)($v['capacity']??''))?>" placeholder="Capacity"><label><input type="checkbox" name="is_active" <?=$v['is_active']?'checked':''?>> Active</label><button class="small-action add-course">Save</button></form></td></tr><?php endforeach;?>
 </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
