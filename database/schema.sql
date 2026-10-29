-- Sync: Smart Course Allocation and Timetable Conflict Resolution System
-- Import this file in phpMyAdmin. It creates the database and core tables.
CREATE DATABASE IF NOT EXISTS sync_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE sync_db;

CREATE TABLE IF NOT EXISTS departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  code VARCHAR(20) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  matric_number VARCHAR(60) NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','lecturer','course_rep','admin') NOT NULL DEFAULT 'student',
  department_id INT UNSIGNED NULL,
  academic_level SMALLINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_users_role_department (role, department_id),
  INDEX idx_users_level (academic_level)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  course_code VARCHAR(30) NOT NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  credit_units TINYINT UNSIGNED NOT NULL DEFAULT 2,
  course_type ENUM('core','elective') NOT NULL DEFAULT 'core',
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_courses_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_courses_department (department_id, is_active)
) ENGINE=InnoDB;

-- Defines the default courses offered to a department at a given level.
CREATE TABLE IF NOT EXISTS department_course_levels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  academic_level SMALLINT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
  academic_session VARCHAR(20) NOT NULL DEFAULT '2026/2027',
  is_default TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_department_level_course_session (course_id, department_id, academic_level, semester, academic_session),
  CONSTRAINT fk_dcl_course FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_dcl_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_dcl_lookup (department_id, academic_level, academic_session)
) ENGINE=InnoDB;

-- Stores the student's current selection; default courses can be removed and restored.
CREATE TABLE IF NOT EXISTS student_courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  academic_session VARCHAR(20) NOT NULL DEFAULT '2026/2027',
  semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
  selection_source ENUM('default','added') NOT NULL DEFAULT 'default',
  registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_course_session_semester (student_id, course_id, academic_session, semester),
  CONSTRAINT fk_student_courses_student FOREIGN KEY (student_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_student_courses_course FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_student_courses_student (student_id, academic_session, semester)
) ENGINE=InnoDB;

-- Lecturers request permission to teach a course; administrators approve or reject.
CREATE TABLE IF NOT EXISTS lecturer_course_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lecturer_id BIGINT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lecturer_course (lecturer_id, course_id),
  CONSTRAINT fk_lcr_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_lcr_course FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_lcr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_lcr_status (status, created_at)
) ENGINE=InnoDB;

-- A class schedule is only accepted after server-side conflict validation.
CREATE TABLE IF NOT EXISTS class_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  lecturer_id BIGINT UNSIGNED NOT NULL,
  scheduled_by BIGINT UNSIGNED NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  venue VARCHAR(120) NOT NULL,
  class_section VARCHAR(80) NOT NULL DEFAULT 'All students',
  academic_session VARCHAR(20) NOT NULL DEFAULT '2026/2027',
  semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('scheduled','needs_review','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_schedule_course FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_schedule_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_schedule_creator FOREIGN KEY (scheduled_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_schedule_day_time (day_of_week, start_time, end_time),
  INDEX idx_schedule_venue_day (venue, day_of_week),
  INDEX idx_schedule_course_session (course_id, academic_session, semester)
) ENGINE=InnoDB;

-- Records conflicts flagged for administrator review; never treat this table as a substitute
-- for validating clashes before inserting or updating class_schedules.
CREATE TABLE IF NOT EXISTS timetable_conflicts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id BIGINT UNSIGNED NULL,
  conflict_type ENUM('course','lecturer','venue','student_group','time_range','other') NOT NULL DEFAULT 'other',
  description TEXT NOT NULL,
  status ENUM('open','reviewing','resolved','unavoidable') NOT NULL DEFAULT 'open',
  reported_by BIGINT UNSIGNED NULL,
  resolved_by BIGINT UNSIGNED NULL,
  resolution_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_conflicts_schedule FOREIGN KEY (schedule_id) REFERENCES class_schedules(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_conflicts_reporter FOREIGN KEY (reported_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_conflicts_resolver FOREIGN KEY (resolved_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_conflicts_status (status, created_at)
) ENGINE=InnoDB;

-- Starter departments only. Replace or extend these with the institution's real departments.
INSERT IGNORE INTO departments (name, code) VALUES
  ('Computer Science', 'CSC'),
  ('Information Technology', 'IT'),
  ('Mathematics', 'MTH'),
  ('Statistics', 'STA');
