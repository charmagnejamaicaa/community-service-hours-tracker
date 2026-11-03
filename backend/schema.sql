-- Community Service Hours Tracker: MySQL schema (Week 13 starter)
-- Run once in MySQL (phpMyAdmin "SQL" tab, MySQL Workbench, or the mysql command line).
-- All sample data below is FICTIONAL.

CREATE DATABASE IF NOT EXISTS service_hours CHARACTER SET utf8mb4;
USE service_hours;

CREATE TABLE IF NOT EXISTS students (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100) NOT NULL,
  required_hours DECIMAL(6,2) NOT NULL DEFAULT 40,
  deadline       DATE NULL
);

CREATE TABLE IF NOT EXISTS activities (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  student_id    INT NOT NULL,
  name          VARCHAR(150) NOT NULL,
  organization  VARCHAR(150) NOT NULL,
  activity_date DATE NOT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE IF NOT EXISTS submissions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  activity_id   INT NOT NULL,
  hours         DECIMAL(5,2) NOT NULL,
  service_date  DATE NOT NULL,
  evidence_note VARCHAR(500) NOT NULL,
  status        ENUM('SUBMITTED','VERIFIED','APPROVED','REJECTED') NOT NULL DEFAULT 'SUBMITTED',
  reject_reason VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES activities(id),
  CHECK (hours > 0 AND hours <= 12)
);

-- Sample (fictional) data
INSERT INTO students (full_name, required_hours, deadline) VALUES
  ('Sample Student One', 40, '2026-12-15'),
  ('Sample Student Two', 40, '2026-11-01');

INSERT INTO activities (student_id, name, organization, activity_date) VALUES
  (1, 'Tree Planting', 'Sample Barangay Council', '2026-10-20'),
  (1, 'Feeding Program', 'Sample Parish Outreach', '2026-10-25'),
  (2, 'Beach Clean-up', 'Sample Youth Group', '2026-10-10');

INSERT INTO submissions (activity_id, hours, service_date, evidence_note, status) VALUES
  (1, 8,   '2026-10-20', 'Signed attendance sheet photo', 'APPROVED'),
  (1, 6,   '2026-10-21', 'Signed attendance sheet photo', 'APPROVED'),
  (2, 8.5, '2026-10-25', 'Supervisor signed logbook',     'APPROVED'),
  (2, 4.5, '2026-10-28', 'Supervisor signed logbook',     'SUBMITTED'),
  (3, 10,  '2026-10-10', 'Attendance sheet',              'APPROVED');
