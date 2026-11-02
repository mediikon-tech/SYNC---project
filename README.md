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
7. Open `http://localhost/sync/` in your browser.

## Create staff accounts for local testing
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
- Role-specific navigation links are available for lecturer and administrator accounts.
- Lecturers can schedule approved courses on any day from Monday to Sunday.
- Assigned course representatives can schedule unscheduled courses in their own department. These classes appear to registered students with the lecturer shown as 'Not assigned' until a lecturer is assigned.
- Both lecturer and representative scheduling check venue, course-section, and overlapping student-course clashes. Conflicting attempts are logged for administrator review.
- Automatic timetable generation and full administrator conflict management remain to be implemented.
