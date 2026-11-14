-- Run this once in phpMyAdmin after importing database/schema.sql.
USE sync_db;

CREATE TABLE IF NOT EXISTS timetable_availability (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  venue VARCHAR(120) NOT NULL,
  academic_session VARCHAR(20) NOT NULL DEFAULT '2026/2027',
  semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_availability_creator FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY uq_availability_slot (day_of_week, start_time, end_time, venue, academic_session, semester),
  INDEX idx_availability_session (academic_session, semester, is_active)
) ENGINE=InnoDB;
