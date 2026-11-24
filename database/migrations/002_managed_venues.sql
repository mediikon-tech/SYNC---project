-- SYNC migration 002: managed teaching venues and existing venue registry.
-- Run once in phpMyAdmin after the existing SYNC schema and timetable_availability table.
USE sync_db;

CREATE TABLE IF NOT EXISTS venues (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  venue_type VARCHAR(60) NOT NULL DEFAULT 'Lecture theatre',
  capacity SMALLINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO venues (name)
SELECT venue FROM class_schedules
WHERE TRIM(venue) <> ''
UNION
SELECT venue FROM timetable_availability
WHERE TRIM(venue) <> '';
