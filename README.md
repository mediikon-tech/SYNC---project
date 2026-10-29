# Sync

**Smart Course Allocation and Timetable Conflict Resolution System**

## Technology
- PHP 8+ (PDO)
- MySQL / MariaDB
- HTML, CSS, JavaScript
- XAMPP for local development

## Project overview
Sync helps students manage course registration and view a weekly timetable. Lecturers can select courses they teach and submit them for administrator approval. Approved lecturers can schedule classes. Two course representatives per department can schedule department courses that have not yet been scheduled by a lecturer.

The scheduling layer must validate course, lecturer, time, venue, and student-group clashes before saving a class. Unavoidable conflicts should be flagged for administrator review.

## Roles
- **Student:** register, select department and level, manage allocated courses, and view/generate a weekly timetable.
- **Lecturer:** request approval to teach courses and schedule approved courses.
- **Course representative:** schedule department courses that have not yet been scheduled by a lecturer.
- **Administrator:** manage users, departments, courses, lecturer approvals, and timetable oversight.

## Run locally with XAMPP
1. Clone or copy this project into `C:\xampp\htdocs\sync`.
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Select **Import**, choose `database/schema.sql` from this project, and run the import. The script creates the `sync_db` database and core tables.
5. The local PDO connection defaults are in `config/database.php`: host `127.0.0.1`, database `sync_db`, username `root`, and an empty password. Adjust these for your local MySQL setup if needed. Environment variables `SYNC_DB_HOST`, `SYNC_DB_NAME`, `SYNC_DB_USER`, and `SYNC_DB_PASS` can override the defaults.
6. Open `http://localhost/sync/` in your browser.

## Database foundation
The initial schema includes:
- departments and user accounts with student, lecturer, course representative, and admin roles
- courses and default course mappings by department, level, semester, and academic session
- each student's saved course selection, allowing default courses to be removed and restored
- lecturer course approval requests
- class schedules with course, lecturer, day, time, venue, section, session, and semester
- timetable conflict records for review and resolution

The schema is a foundation; it does not by itself implement authentication, course-selection persistence, timetable generation, or conflict validation. Those behaviours must be implemented in the PHP application before the system is treated as production-ready.

## Current development status
- UI prototype: dashboard, course management, and weekly timetable (these pages still contain sample data).
- Database schema and reusable PDO connection: added.
- Student registration and sign-in now use the database, password hashing, sessions, CSRF tokens, and server-side validation.
- Workspace pages require sign-in; logout uses a POST request with a CSRF token.
- Saved course-management UI, timetable generation, clash validation, lecturer workflows, and admin tools still need implementation.
