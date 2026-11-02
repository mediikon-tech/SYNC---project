-- Apply once to an existing Sync database.
-- Allows course representatives to schedule a class before a lecturer is assigned.
ALTER TABLE class_schedules
  MODIFY lecturer_id BIGINT UNSIGNED NULL;

ALTER TABLE class_schedules
  DROP FOREIGN KEY fk_schedule_lecturer,
  ADD CONSTRAINT fk_schedule_lecturer
    FOREIGN KEY (lecturer_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS department_representatives (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  representative_user_id BIGINT UNSIGNED NOT NULL,
  position TINYINT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_department_rep_position (department_id, position),
  UNIQUE KEY uq_rep_user (representative_user_id),
  CONSTRAINT fk_rep_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_rep_user FOREIGN KEY (representative_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_rep_position CHECK (position IN (1, 2))
) ENGINE=InnoDB;
