# Sync

Smart Course Allocation and Timetable Conflict Resolution System.

## Technology
- PHP
- MySQL
- HTML, CSS, JavaScript
- XAMPP for local development

## Project overview
Sync helps students manage course registration and view a weekly timetable. Lecturers can select courses they teach and submit them for administrator approval. Approved lecturers can schedule classes. Two course representatives per department can schedule department courses that have not yet been scheduled by a lecturer.

The system validates course, lecturer, time, and venue conflicts before saving a class. If a conflict cannot be resolved, it flags it for review.

## Planned roles
- Student: register, select department and level, manage allocated courses, and view/generate a weekly timetable.
- Lecturer: select courses, request approval, and schedule approved courses.
- Course representative: schedule unscheduled courses within their department.
- Administrator: manage users, departments, courses, lecturers, approvals, and timetable oversight.

## Run locally
1. Install XAMPP and start Apache and MySQL.
2. Place the project folder inside `C:\xampp\htdocs\sync`.
3. Create a MySQL database for Sync and import the SQL schema when added.
4. Open `http://localhost/sync/` in your browser.

## Development status
Initial project setup. Authentication, database schema, scheduling logic, and admin tools will be implemented in subsequent steps.