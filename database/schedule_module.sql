-- Doctor Availability Scheduling Module SQL
-- Creates the Schedule table used for doctor availability.

CREATE TABLE IF NOT EXISTS Schedule (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
