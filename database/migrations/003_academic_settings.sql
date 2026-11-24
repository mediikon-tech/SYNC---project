-- SYNC migration 003: centrally managed academic session and semester.
USE sync_db;

CREATE TABLE IF NOT EXISTS academic_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  academic_session VARCHAR(20) NOT NULL,
  semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_academic_settings_user FOREIGN KEY (updated_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_academic_settings_singleton CHECK (id = 1),
  CONSTRAINT chk_academic_settings_semester CHECK (semester IN (1, 2))
) ENGINE=InnoDB;

INSERT IGNORE INTO academic_settings (id, academic_session, semester)
VALUES (1, '2026/2027', 1);
