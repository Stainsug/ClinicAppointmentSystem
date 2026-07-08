-- Clinic Appointment Booking System Database Script
-- Compatible with MySQL 8+

DROP DATABASE IF EXISTS clinic_appointment_system;
CREATE DATABASE clinic_appointment_system;
USE clinic_appointment_system;

-- ----------------------------
-- Table: Patient
-- ----------------------------
CREATE TABLE Patient (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ----------------------------
-- Table: Doctor
-- ----------------------------
CREATE TABLE Doctor (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ----------------------------
-- Table: Schedule
-- ----------------------------
CREATE TABLE Schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    available_date DATE NOT NULL,
    available_time TIME NOT NULL,
    CONSTRAINT fk_schedule_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES Doctor(doctor_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_doctor_slot
        UNIQUE (doctor_id, available_date, available_time)
) ENGINE=InnoDB;

-- ----------------------------
-- Table: Appointment
-- ----------------------------
CREATE TABLE Appointment (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    schedule_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    status ENUM('Booked', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Booked',
    CONSTRAINT fk_appointment_patient
        FOREIGN KEY (patient_id)
        REFERENCES Patient(patient_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_appointment_schedule
        FOREIGN KEY (schedule_id)
        REFERENCES Schedule(schedule_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_patient_schedule
        UNIQUE (patient_id, schedule_id)
) ENGINE=InnoDB;

-- ----------------------------
-- Table: Admin
-- ----------------------------
CREATE TABLE Admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ----------------------------
-- Sample Data: Patient
-- ----------------------------
INSERT INTO Patient (fullname, email, phone, password) VALUES
('John Carter', 'john.carter@example.com', '555-1001', 'john123'),
('Mary Lewis', 'mary.lewis@example.com', '555-1002', 'mary123'),
('Ahmed Khan', 'ahmed.khan@example.com', '555-1003', 'ahmed123');

-- ----------------------------
-- Sample Data: Doctor
-- ----------------------------
INSERT INTO Doctor (fullname, specialization, email) VALUES
('Dr. Sarah Wilson', 'Cardiology', 'sarah.wilson@clinic.com'),
('Dr. Daniel Reyes', 'Dermatology', 'daniel.reyes@clinic.com'),
('Dr. Nina Patel', 'Pediatrics', 'nina.patel@clinic.com');

-- ----------------------------
-- Sample Data: Schedule
-- ----------------------------
INSERT INTO Schedule (doctor_id, available_date, available_time) VALUES
(1, '2026-06-24', '09:00:00'),
(1, '2026-06-24', '10:00:00'),
(2, '2026-06-25', '14:00:00'),
(2, '2026-06-25', '15:00:00'),
(3, '2026-06-26', '11:00:00'),
(3, '2026-06-26', '12:00:00');

-- ----------------------------
-- Sample Data: Appointment
-- ----------------------------
INSERT INTO Appointment (patient_id, schedule_id, appointment_date, status) VALUES
(1, 1, '2026-06-24', 'Booked'),
(2, 3, '2026-06-25', 'Completed'),
(3, 5, '2026-06-26', 'Cancelled');

-- ----------------------------
-- Sample Data: Admin
-- ----------------------------
INSERT INTO Admin (username, password) VALUES
('admin', 'admin123'),
('superadmin', 'superadmin123');
