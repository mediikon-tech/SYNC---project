# Sync

**Smart Course Allocation and Timetable Conflict Resolution System**

## Technology
- PHP 8+ with PDO
- MySQL / MariaDB
- HTML, CSS, JavaScript
- XAMPP for local development

## Project overview
Sync helps students manage course registration and view a weekly timetable. Lecturers request courses they want to teach. Administrators approve or reject those requests. Approved lecturers can then schedule their courses. Two course representatives per department can schedule department courses that have not yet been scheduled by a lecturer.

The scheduling layer must validate course, lecturer, time, venue, and student-group clashes before saving a class. Unavoidable conflicts should be flagged for administrator review.

## Roles
- **Student:** register, select department and level, manage allocated courses, and view a weekly timetable.
- **Lecturer:** request approval to teach courses and, after approval, schedule approved courses.
- **Course representative:** schedule department courses that have not yet been scheduled by a lecturer.
- **Administrator:** manage users, departments, courses, lecturer approvals, and timetable oversight.

## Run locally with XAMPP
1. Clone or copy this project into `C:\xampp\htdocs\sync`.
2. Open XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Select **Import**, choose `database/schema.sql`, and run it. The script creates the `sync_db` database and core tables.
5. Optional: import `database/demo_data.sql` to add illustrative Computer Science courses for 400 Level, Semester 1. Replace these examples with your institution's approved curriculum.
6. The local PDO defaults are in `config/database.php`: host `127.0.0.1`, database `sync_db`, username `root`, and an empty password. Environment variables `SYNC_DB_HOST`, `SYNC_DB_NAME`, `SYNC_DB_USER`, and `SYNC_DB_PASS` can override these defaults.
7. If you already imported an earlier version of the Sync database, open phpMyAdmin, select `sync_db`, choose the SQL tab, and run `database/migrations/001_course_rep_scheduling.sql`. This migration makes the lecturer field optional for representative-created classes and creates the two representative positions per department. Do not run this migration on a fresh database created from the updated schema, because those changes are already included.
8. If you already have an older database, import `database/timetable_availability.sql` in phpMyAdmin. Fresh databases created from the updated `database/schema.sql` already include this table.
9. Open `http://localhost/sync/` in your browser.

## Development test accounts
Import `database/test_accounts.sql` in phpMyAdmin after importing `database/schema.sql`. This creates or resets five local test accounts. Every account uses the same password.

| Role | Email |
|---|---|
| Student | `stu@sync.test` |
| Lecturer | `lec@sync.test` |
| Course representative 1 | `rep1@sync.test` |
| Course representative 2 | `rep2@sync.test` |
| Administrator | `admin@sync.test` |

**Password for all five accounts:** `Sync123`

If login says the password is incorrect, re-import `database/test_accounts.sql` into the `sync_db` database. The script resets the password hash and reactivates these five accounts. Confirm that the five rows appear in the SQL result at the bottom of the script. These credentials are for local development only. Do not use them on a public deployment.

## Create other staff accounts for local testing
Public registration creates student accounts only. This prevents visitors from granting themselves lecturer or administrator privileges.

1. Register a normal account through the Sync registration page.
2. Open phpMyAdmin, select `sync_db`, and open the SQL tab.
3. To convert an account into a lecturer, replace the email and department code below with the registered account's email and the correct department code:

```sql
UPDATE users
SET role = 'lecturer',
    department_id = (SELECT id FROM departments WHERE code = 'CSC')
WHERE email = 'lecturer@example.com';
```

4. To create an administrator account, register a separate account and run:

```sql
UPDATE users
SET role = 'admin', department_id = NULL, academic_level = NULL
WHERE email = 'admin@example.com';
```

5. To assign a course representative, register a separate account, then set its role and department. Replace the email and department code:

```sql
UPDATE users
SET role = 'course_rep',
    department_id = (SELECT id FROM departments WHERE code = 'CSC'),
    academic_level = NULL
WHERE email = 'rep1@example.com';
```

6. Assign the account to one of the department's two representative positions. Use position 1 for the first representative and position 2 for the second:

```sql
INSERT INTO department_representatives (department_id, representative_user_id, position)
SELECT d.id, u.id, 1
FROM departments d
JOIN users u ON u.email = 'rep1@example.com' AND u.role = 'course_rep'
WHERE d.code = 'CSC';
```

Repeat with the second representative's email and `position = 2`. Database constraints prevent a third position or duplicate assignment.

7. Sign out and sign in again so the new role is loaded into the session. Use real, unique email addresses. Keep administrator access restricted.

## Database foundation
The schema includes:
- departments and user accounts with student, lecturer, course representative, and admin roles
- courses and default course mappings by department, level, semester, and academic session
- student course selections, including the ability to remove and restore default courses
- lecturer course approval requests
- class schedules with course, optional lecturer, scheduling user, day, time, venue, section, session, and semester
- two fixed course representative positions per department, used only for courses without an existing scheduled class
- timetable conflict records for review and resolution

## Current development status
- Student registration and sign-in use MySQL, password hashing, PHP sessions, CSRF tokens, and server-side validation.
- Students can add, remove, and restore courses mapped to their department and academic level.
- The weekly timetable reads scheduled classes for a student's selected courses.
- Lecturers can request courses in their department.
- Administrators can approve or reject pending lecturer course requests.
- Role-specific navigation links are available for lecturers, course representatives, and administrators.
- Lecturers can schedule approved courses on any day from Monday to Sunday.
- Assigned course representatives can schedule unscheduled courses in their own department. These classes appear to registered students with the lecturer shown as 'Not assigned' until a lecturer is assigned.
- Both lecturer and representative scheduling check venue, course-section, and overlapping student-course clashes. Conflicting attempts are logged for administrator review.
- Administrators have a dedicated conflict management page with status filters, case details, review notes, and resolved/unavoidable decisions.
- Scheduling conflicts from lecturer and representative attempts are saved with their detected conflict category and displayed for administrator review.
- Administrators can configure available day, time, and venue combinations, then generate a timetable for courses with approved lecturers. Existing schedules remain unchanged, and courses that cannot fit are logged for administrator review.
- The dashboard now loads live database information for students, lecturers, course representatives, and administrators instead of displaying sample classes and placeholder totals.
- The weekly timetable now provides role-based views: students see their registered courses, lecturers see their assigned classes, representatives see their department's classes, and administrators see all scheduled classes.
