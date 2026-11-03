-- Adds the notifications table. Run this ONCE in phpMyAdmin (SQL tab) after schema.sql.
-- It does not touch your existing tables or data.

USE service_hours;

CREATE TABLE IF NOT EXISTS notifications (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  notif_key       VARCHAR(150) NOT NULL UNIQUE,   -- type:studentId:referenceId:date (prevents duplicates)
  type            VARCHAR(50)  NOT NULL,
  student_id      INT NOT NULL,
  reference_id    VARCHAR(100) NOT NULL,
  message         VARCHAR(500) NULL,
  status          ENUM('PENDING','RETRYING','SENT','MANUAL_REVIEW','HANDLED') NOT NULL DEFAULT 'PENDING',
  attempts        INT NOT NULL DEFAULT 0,
  last_error      VARCHAR(100) NULL,
  next_attempt_at DATETIME NULL,
  handled_at      DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  INDEX idx_notifications_status (status)
);
