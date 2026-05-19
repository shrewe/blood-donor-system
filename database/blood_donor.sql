-- ============================================================
-- Blood Donor Finder System - Database Schema
-- Compatible with MySQL 5.7+ / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS blood_donor_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blood_donor_system;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('donor','patient','hospital') NOT NULL DEFAULT 'donor',
  `phone`      VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: donors
-- ============================================================
CREATE TABLE IF NOT EXISTS `donors` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`             INT UNSIGNED NOT NULL UNIQUE,
  `blood_group`         ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `city`                VARCHAR(100) NOT NULL,
  `latitude`            DECIMAL(10,7) DEFAULT NULL,
  `longitude`           DECIMAL(10,7) DEFAULT NULL,
  `availability_status` ENUM('available_now','available_later','not_available') NOT NULL DEFAULT 'available_now',
  `last_donation_date`  DATE DEFAULT NULL,
  `reliability_score`   INT UNSIGNED NOT NULL DEFAULT 0,
  `total_donations`     INT UNSIGNED NOT NULL DEFAULT 0,
  `sos_responses`       INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT `fk_donor_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: emergency_requests
-- ============================================================
CREATE TABLE IF NOT EXISTS `emergency_requests` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `blood_group`    ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `location`       VARCHAR(200) NOT NULL,
  `city`           VARCHAR(100) NOT NULL,
  `latitude`       DECIMAL(10,7) DEFAULT NULL,
  `longitude`      DECIMAL(10,7) DEFAULT NULL,
  `patient_name`   VARCHAR(100) DEFAULT NULL,
  `contact_number` VARCHAR(20)  DEFAULT NULL,
  `notes`          TEXT DEFAULT NULL,
  `requested_by`   INT UNSIGNED NOT NULL,
  `status`         ENUM('active','fulfilled','cancelled') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_req_user` FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `donor_id`   INT UNSIGNED NOT NULL,
  `request_id` INT UNSIGNED DEFAULT NULL,
  `message`    TEXT NOT NULL,
  `status`     ENUM('unread','read','accepted','rejected') NOT NULL DEFAULT 'unread',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_donor` FOREIGN KEY (`donor_id`)   REFERENCES `donors`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_req`   FOREIGN KEY (`request_id`) REFERENCES `emergency_requests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: donations
-- ============================================================
CREATE TABLE IF NOT EXISTS `donations` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `donor_id`    INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED DEFAULT NULL,
  `request_id`  INT UNSIGNED DEFAULT NULL,
  `date`        DATE NOT NULL,
  `status`      ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `notes`       TEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_don_donor`    FOREIGN KEY (`donor_id`)    REFERENCES `donors`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_don_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `users`(`id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_don_req`      FOREIGN KEY (`request_id`)  REFERENCES `emergency_requests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: hospitals (extra info for hospital users)
-- ============================================================
CREATE TABLE IF NOT EXISTS `hospitals` (
  `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `city`    VARCHAR(100) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_hosp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Passwords are all: "password" (bcrypt cost 10)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`) VALUES
('Rahul Sharma',    'rahul@example.com',   '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV', 'donor',    '+91-9876543210'),
('Priya Singh',     'priya@example.com',   '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV', 'donor',    '+91-9812345678'),
('Amit Patel',      'amit@example.com',    '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV', 'donor',    '+91-9823456789'),
('Sunita Verma',    'sunita@example.com',  '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV', 'donor',    '+91-9834567890'),
('Karan Mehta',     'karan@example.com',   '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV', 'patient',  '+91-9845678901'),
('City Hospital',   'cityhospital@example.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV', 'hospital', '+91-0832123456'),
('Apollo Blood Bank','apollo@example.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV', 'hospital', '+91-0832654321');

INSERT INTO `donors` (`user_id`,`blood_group`,`city`,`latitude`,`longitude`,`availability_status`,`last_donation_date`,`reliability_score`,`total_donations`,`sos_responses`) VALUES
(1, 'O+',  'Goa',    15.2993, 74.1240, 'available_now',    '2025-09-01', 35, 3, 1),
(2, 'B+',  'Goa',    15.3173, 74.0833, 'available_now',    '2025-11-15', 20, 2, 0),
(3, 'A+',  'Mumbai', 19.0760, 72.8777, 'available_later',  '2025-12-20', 50, 4, 2),
(4, 'AB-', 'Goa',    15.2815, 74.0030, 'not_available',    '2026-01-10',  5, 0, 1);

INSERT INTO `hospitals` (`user_id`,`city`,`address`) VALUES
(6, 'Goa',    'MG Road, Panaji, Goa'),
(7, 'Mumbai', 'Andheri West, Mumbai');

-- ============================================================
-- INDEXES for performance
-- ============================================================
CREATE INDEX idx_donor_blood_group  ON donors(blood_group);
CREATE INDEX idx_donor_city         ON donors(city);
CREATE INDEX idx_donor_avail        ON donors(availability_status);
CREATE INDEX idx_notif_donor_status ON notifications(donor_id, status);
CREATE INDEX idx_req_status         ON emergency_requests(status);
