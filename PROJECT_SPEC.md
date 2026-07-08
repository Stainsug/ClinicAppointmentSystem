# Web-Based Clinic Appointment Booking System

## Technology Stack
- HTML
- CSS
- JavaScript
- PHP
- MySQL
- XAMPP

## Users
1. Patient
2. Doctor
3. Admin

## Main Features

### Patient
- Register
- Login
- View doctor availability
- Book appointments
- View appointments
- Cancel appointments

### Doctor
- Login
- View appointments
- Update availability

### Admin
- Login
- Manage doctors
- Manage appointments
- Generate reports

## Database Tables

Patient
- patient_id (PK)
- fullname
- email
- phone
- password

Doctor
- doctor_id (PK)
- fullname
- specialization
- email

Schedule
- schedule_id (PK)
- doctor_id (FK)
- available_date
- available_time

Appointment
- appointment_id (PK)
- patient_id (FK)
- schedule_id (FK)
- appointment_date
- status

Admin
- admin_id (PK)
- username
- password