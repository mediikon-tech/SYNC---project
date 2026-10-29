-- OPTIONAL DEMONSTRATION DATA ONLY.
-- These are illustrative Computer Science courses for testing the course-selection UI.
-- Replace them with the real curriculum and approved course mappings for your institution.
-- Import database/schema.sql first, then import this file in phpMyAdmin.
USE sync_db;

INSERT IGNORE INTO courses (department_id, course_code, title, credit_units, course_type, description, is_active)
SELECT d.id, demo.course_code, demo.title, demo.credit_units, demo.course_type,
       'Illustrative demo record; replace with the approved institutional course details.', 1
FROM departments d
JOIN (
    SELECT 'CSC 401' AS course_code, 'Software Engineering' AS title, 4 AS credit_units, 'core' AS course_type
    UNION ALL SELECT 'CSC 405', 'Database Systems', 3, 'core'
    UNION ALL SELECT 'CSC 409', 'Computer Networks', 3, 'core'
    UNION ALL SELECT 'CSC 411', 'Artificial Intelligence', 3, 'elective'
    UNION ALL SELECT 'CSC 415', 'Research Methods', 2, 'core'
    UNION ALL SELECT 'CSC 417', 'Project Management', 2, 'elective'
) AS demo
WHERE d.code = 'CSC';

-- Demonstration mappings are for 400 Level, Semester 1, 2026/2027 only.
INSERT IGNORE INTO department_course_levels
    (course_id, department_id, academic_level, semester, academic_session, is_default)
SELECT c.id, c.department_id, 400, 1, '2026/2027', 1
FROM courses c
JOIN departments d ON d.id = c.department_id
WHERE d.code = 'CSC'
  AND c.course_code IN ('CSC 401', 'CSC 405', 'CSC 409', 'CSC 411', 'CSC 415', 'CSC 417');
