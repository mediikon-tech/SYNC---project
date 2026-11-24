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

$pageTitle = 'Manage class schedules';
$activePage = 'admin_classes';
$session = '2026/2027';
$semester = 1;
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$error = '';
$success = '';
$editing = null;
$venues = $pdo->query('SELECT name FROM venues WHERE is_active=1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$courses = $pdo->query('SELECT id,course_code,title FROM courses WHERE is_active=1 ORDER BY course_code')->fetchAll();
$lecturers = $pdo->query("SELECT id,full_name,email FROM users WHERE role='lecturer' AND is_active=1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'cancel') {
        if (!$id) {
            $error = 'Choose a valid class schedule.';
        } else {
            $q = $pdo->prepare("UPDATE class_schedules SET status='cancelled' WHERE id=? AND academic_session=? AND semester=?");
            $q->execute([$id,$session,$semester]);
            $success = $q->rowCount() ? 'Class cancelled. Its record has been retained.' : 'Class not found or already cancelled.';
        }
    } elseif (in_array($action,['create','update'],true)) {
        $courseId = filter_var($_POST['course_id'] ?? null,FILTER_VALIDATE_INT);
        $lecturerId = ($_POST['lecturer_id'] ?? '') === '' ? null : filter_var($_POST['lecturer_id'],FILTER_VALIDATE_INT);
        $day = (string)($_POST['day_of_week'] ?? '');
        $start = (string)($_POST['start_time'] ?? '');
        $end = (string)($_POST['end_time'] ?? '');
        $venue = (string)($_POST['venue'] ?? '');
        $section = trim((string)($_POST['class_section'] ?? 'All students'));
        $status = (string)($_POST['status'] ?? 'scheduled');
        $validTime = static fn(string $v): bool => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$v) === 1;
        if (!$courseId || !in_array($day,$days,true) || !$validTime($start) || !$validTime($end) || $end <= $start ||
            !in_array($venue,$venues,true) || $section === '' || strlen($section)>80 ||
            !in_array($status,['scheduled','cancelled'],true) ||
            ($lecturerId !== null && !$lecturerId)) {
            $error = 'Complete all class details and choose an active course, venue, and valid lecturer.';
        } else {
            try {
                $courseCheck=$pdo->prepare('SELECT id FROM courses WHERE id=? AND is_active=1');
                $courseCheck->execute([$courseId]);
                if (!$courseCheck->fetchColumn()) throw new RuntimeException('The selected course is not active.');
                if ($lecturerId !== null) {
                    $lecturerCheck=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='lecturer' AND is_active=1");
                    $lecturerCheck->execute([$lecturerId]);
                    if (!$lecturerCheck->fetchColumn()) throw new RuntimeException('The selected lecturer is not active.');
                }

                if ($status === 'scheduled') {
                    $clash=$pdo->prepare(
                        "SELECT s.id, s.venue, s.lecturer_id, s.course_id
                         FROM class_schedules s
                         WHERE s.academic_session=? AND s.semester=? AND s.status='scheduled'
                           AND s.day_of_week=? AND s.start_time < ? AND s.end_time > ?
                           AND s.id <> ?
                           AND (
                             LOWER(s.venue)=LOWER(?)
                             OR (? IS NOT NULL AND s.lecturer_id=?)
                             OR (s.course_id=? AND s.class_section=?)
                             OR EXISTS (
                               SELECT 1 FROM student_courses target_sc
                               JOIN student_courses other_sc
                                 ON other_sc.student_id=target_sc.student_id
                                AND other_sc.academic_session=target_sc.academic_session
                                AND other_sc.semester=target_sc.semester
                               WHERE target_sc.course_id=? AND other_sc.course_id=s.course_id
                                 AND target_sc.academic_session=s.academic_session
                                 AND target_sc.semester=s.semester
                             )
                           )
                         LIMIT 1"
                    );
                    $clash->execute([$session,$semester,$day,$end,$start,$id,$venue,$lecturerId,$lecturerId,$courseId,$section,$courseId]);
                    $found=$clash->fetch();
                    if ($found) {
                        $reason = strcasecmp((string)$found['venue'],$venue)===0 ? 'venue' :
                            (($lecturerId !== null && (int)$found['lecturer_id']===$lecturerId) ? 'lecturer' :
                            ((int)$found['course_id']===$courseId ? 'course section' : 'student group'));
                        throw new RuntimeException('This change conflicts with another scheduled class (' . $reason . '). Change the time, venue, lecturer, or section.');
                    }
                }

                if ($action === 'create') {
                    $q=$pdo->prepare(
                        'INSERT INTO class_schedules (course_id,lecturer_id,scheduled_by,day_of_week,start_time,end_time,venue,class_section,academic_session,semester,status)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $q->execute([$courseId,$lecturerId,(int)$admin['id'],$day,$start,$end,$venue,$section,$session,$semester,$status]);
                    $success='Class schedule created.';
                } else {
                    if (!$id) throw new RuntimeException('Choose a class schedule to edit.');
                    $q=$pdo->prepare(
                        'UPDATE class_schedules SET course_id=?,lecturer_id=?,day_of_week=?,start_time=?,end_time=?,venue=?,class_section=?,status=?
                         WHERE id=? AND academic_session=? AND semester=?'
                    );
                    $q->execute([$courseId,$lecturerId,$day,$start,$end,$venue,$section,$status,$id,$session,$semester]);
                    $success='Class schedule updated.';
                }
            } catch (Throwable $ex) {
                if ($ex instanceof RuntimeException) $error=$ex->getMessage();
                else {
                    error_log('Sync admin class schedule save failed: '.$ex->getMessage());
                    $error='The class schedule could not be saved.';
                }
            }
        }
    } else {
        $error='Unknown class schedule action.';
    }
}

if (isset($_GET['edit'])) {
    $editId=filter_var($_GET['edit'],FILTER_VALIDATE_INT);
    if ($editId) {
        $q=$pdo->prepare(
            "SELECT s.*,c.course_code,c.title,lecturer.full_name AS lecturer_name
             FROM class_schedules s JOIN courses c ON c.id=s.course_id
             LEFT JOIN users lecturer ON lecturer.id=s.lecturer_id
             WHERE s.id=? AND s.academic_session=? AND s.semester=?"
        );
        $q->execute([$editId,$session,$semester]);
        $editing=$q->fetch() ?: null;
    }
}
$list=$pdo->prepare(
    "SELECT s.id,s.day_of_week,s.start_time,s.end_time,s.venue,s.class_section,s.status,
            c.course_code,c.title,COALESCE(u.full_name,'Not assigned') AS lecturer_name
     FROM class_schedules s JOIN courses c ON c.id=s.course_id
     LEFT JOIN users u ON u.id=s.lecturer_id
     WHERE s.academic_session=? AND s.semester=?
     ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),s.start_time"
);
$list->execute([$session,$semester]);
$classes=$list->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row"><div><p class="eyebrow">ADMINISTRATION</p><h1>Manage class schedules</h1><p class="muted">Add, edit, or cancel classes. Every saved change is checked for timetable clashes.</p></div><div class="date-chip"><?=e($session)?> · Semester <?=$semester?></div></div>
<?php if($error!==''):?><p class="form-feedback" role="alert"><?=e($error)?></p><?php endif;?>
<?php if($success!==''):?><p class="success-feedback" role="status"><?=e($success)?></p><?php endif;?>
<section class="panel schedule-form-panel">
 <div class="panel-heading"><div><h2><?=$editing?'Edit class':'Add a class'?></h2><p class="muted">Administrators can directly maintain the saved timetable.</p></div><?php if($editing):?><a class="text-link" href="admin_classes.php">Cancel editing</a><?php endif;?></div>
 <form method="post" action="admin_classes.php<?= $editing?'?edit='.(int)$editing['id']:'' ?>" class="schedule-form">
  <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=$editing?'update':'create'?>"><?php if($editing):?><input type="hidden" name="id" value="<?=(int)$editing['id']?>"><?php endif;?>
  <div class="form-grid">
   <div class="field-group field-full"><label>Course</label><select name="course_id" required><option value="">Choose course</option><?php foreach($courses as $c):?><option value="<?=(int)$c['id']?>" <?=((int)($editing['course_id']??0)===(int)$c['id'])?'selected':''?>><?=e($c['course_code'].' · '.$c['title'])?></option><?php endforeach;?></select></div>
   <div class="field-group"><label>Lecturer</label><select name="lecturer_id"><option value="">Not assigned</option><?php foreach($lecturers as $l):?><option value="<?=(int)$l['id']?>" <?=((int)($editing['lecturer_id']??0)===(int)$l['id'])?'selected':''?>><?=e($l['full_name'].' · '.$l['email'])?></option><?php endforeach;?></select></div>
   <div class="field-group"><label>Day</label><select name="day_of_week" required><?php foreach($days as $d):?><option value="<?=e($d)?>" <?=($editing['day_of_week']??'Monday')===$d?'selected':''?>><?=e($d)?></option><?php endforeach;?></select></div>
   <div class="field-group"><label>Start time</label><input type="time" name="start_time" value="<?=e(substr((string)($editing['start_time']??''),0,5))?>" required></div>
   <div class="field-group"><label>End time</label><input type="time" name="end_time" value="<?=e(substr((string)($editing['end_time']??''),0,5))?>" required></div>
   <div class="field-group"><label>Venue</label><select name="venue" required><option value="">Choose venue</option><?php foreach($venues as $v):?><option value="<?=e($v)?>" <?=($editing['venue']??'')===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
   <div class="field-group"><label>Class section</label><input name="class_section" maxlength="80" value="<?=e($editing['class_section']??'All students')?>" required></div>
   <div class="field-group"><label>Status</label><select name="status"><option value="scheduled" <?=($editing['status']??'scheduled')==='scheduled'?'selected':''?>>Scheduled</option><option value="cancelled" <?=($editing['status']??'')==='cancelled'?'selected':''?>>Cancelled</option></select></div>
  </div>
  <button class="primary-button" type="submit"><?=$editing?'Save class changes':'Add class to timetable'?></button>
 </form>
</section>
<section class="panel class-list-panel"><div class="panel-heading"><div><h2>Current timetable records</h2><p class="muted">Cancelled classes remain in the database for record keeping.</p></div><span class="demo-pill"><?=count($classes)?> CLASSES</span></div>
 <?php if(!$classes):?><div class="empty-state">No classes have been scheduled for this semester.</div><?php else:?><div class="table-scroll"><table class="data-table"><thead><tr><th>Course</th><th>Day/time</th><th>Venue</th><th>Lecturer</th><th>Section</th><th>Status</th><th>Actions</th></tr></thead><tbody>
 <?php foreach($classes as $cl):?><tr><td><strong><?=e($cl['course_code'])?></strong><small><?=e($cl['title'])?></small></td><td><?=e($cl['day_of_week'])?><small><?=e(date('g:i A',strtotime($cl['start_time'])).' to '.date('g:i A',strtotime($cl['end_time'])))?></small></td><td><?=e($cl['venue'])?></td><td><?=e($cl['lecturer_name'])?></td><td><?=e($cl['class_section'])?></td><td><?=e(ucfirst($cl['status']))?></td><td><a class="text-link" href="admin_classes.php?edit=<?=(int)$cl['id']?>">Edit</a><?php if($cl['status']==='scheduled'):?><form method="post" class="inline-action-form" onsubmit="return confirm('Cancel this class?');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?=(int)$cl['id']?>"><button class="small-action remove-course">Cancel</button></form><?php endif;?></td></tr><?php endforeach;?>
 </tbody></table></div><?php endif;?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
