-- SYNC development test accounts
-- Run this after importing database/schema.sql.
-- Test password for every account: Sync123
-- These accounts are for local development only. Do not use them in production.

USE sync_db;

SET @password_hash = '$2y$12$k/ZALl62W13cEw0ADmYimuqMpeu.fCjsjS/lklIBhV3idqbjqhcbe';
SET @department_id = (SELECT id FROM departments WHERE code = 'CSC' LIMIT 1);

INSERT INTO users (full_name, email, password_hash, role, department_id, academic_level, is_active)
VALUES
  ('SYNC Test Student', 'stu@sync.test', @password_hash, 'student', @department_id, 400, 1),
  ('SYNC Test Lecturer', 'lec@sync.test', @password_hash, 'lecturer', @department_id, NULL, 1),
  ('SYNC Test Representative 1', 'rep1@sync.test', @password_hash, 'course_rep', @department_id, NULL, 1),
  ('SYNC Test Representative 2', 'rep2@sync.test', @password_hash, 'course_rep', @department_id, NULL, 1),
  ('SYNC Test Administrator', 'admin@sync.test', @password_hash, 'admin', @department_id, NULL, 1)
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  department_id = VALUES(department_id),
  academic_level = VALUES(academic_level),
  is_active = 1;

-- Assign the two representative accounts to the first two CSC positions.
-- INSERT IGNORE preserves any representative assignments that already occupy these slots.
INSERT IGNORE INTO department_representatives (department_id, representative_user_id, position)
SELECT @department_id, u.id, 1
FROM users u
WHERE u.email = 'rep1@sync.test' AND @department_id IS NOT NULL;

INSERT IGNORE INTO department_representatives (department_id, representative_user_id, position)
SELECT @department_id, u.id, 2
FROM users u
WHERE u.email = 'rep2@sync.test' AND @department_id IS NOT NULL;

-- Confirm the accounts and representative assignments.
SELECT id, full_name, email, role, department_id, academic_level, is_active
FROM users
WHERE email IN (
  'stu@sync.test',
  'lec@sync.test',
  'rep1@sync.test',
  'rep2@sync.test',
  'admin@sync.test'
)
ORDER BY FIELD(role, 'student', 'lecturer', 'course_rep', 'admin'), email;

SELECT dr.position, u.email, d.code AS department
FROM department_representatives dr
JOIN users u ON u.id = dr.representative_user_id
JOIN departments d ON d.id = dr.department_id
WHERE d.code = 'CSC'
ORDER BY dr.position;
