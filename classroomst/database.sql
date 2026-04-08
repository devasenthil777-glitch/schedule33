-- ============================================
-- Smart Timetable & Schedule Management System
-- Database Schema + Sample Data
-- ============================================

CREATE DATABASE IF NOT EXISTS smart_timetable;
USE smart_timetable;

-- -------------------------
-- Table: departments
-- -------------------------
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------
-- Table: faculty
-- -------------------------
CREATE TABLE IF NOT EXISTS faculty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    department_id INT,
    specialization VARCHAR(150),
    max_hours_per_week INT DEFAULT 20,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- -------------------------
-- Table: subjects
-- -------------------------
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    department_id INT,
    semester INT,
    credits INT DEFAULT 3,
    hours_per_week INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- -------------------------
-- Table: classrooms
-- -------------------------
CREATE TABLE IF NOT EXISTS classrooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    building VARCHAR(50),
    capacity INT DEFAULT 30,
    room_type ENUM('lecture','lab','seminar','auditorium') DEFAULT 'lecture',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------
-- Table: time_slots
-- -------------------------
CREATE TABLE IF NOT EXISTS time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL
);

-- -------------------------
-- Table: allocations (Faculty <-> Subject)
-- -------------------------
CREATE TABLE IF NOT EXISTS allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    subject_id INT NOT NULL,
    semester INT,
    academic_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_alloc (faculty_id, subject_id, academic_year),
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- -------------------------
-- Table: timetables
-- -------------------------
CREATE TABLE IF NOT EXISTS timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    semester INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    time_slot_id INT NOT NULL,
    subject_id INT NOT NULL,
    faculty_id INT NOT NULL,
    classroom_id INT NOT NULL,
    class_section VARCHAR(10) DEFAULT 'A',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (faculty_id) REFERENCES faculty(id),
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id)
);

-- -------------------------
-- Table: users (Login system)
-- -------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','faculty') DEFAULT 'admin',
    faculty_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE SET NULL
);

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Departments
INSERT INTO departments (name, code) VALUES
('Computer Science & Engineering', 'CSE'),
('Electronics & Communication', 'ECE'),
('Mechanical Engineering', 'MECH'),
('Information Technology', 'IT');

-- Time Slots
INSERT INTO time_slots (label, start_time, end_time) VALUES
('Period 1', '08:00:00', '09:00:00'),
('Period 2', '09:00:00', '10:00:00'),
('Period 3', '10:15:00', '11:15:00'),
('Period 4', '11:15:00', '12:15:00'),
('Period 5', '13:00:00', '14:00:00'),
('Period 6', '14:00:00', '15:00:00'),
('Period 7', '15:15:00', '16:15:00');

-- Classrooms
INSERT INTO classrooms (room_number, building, capacity, room_type) VALUES
('101', 'Block A', 60, 'lecture'),
('102', 'Block A', 60, 'lecture'),
('103', 'Block A', 40, 'seminar'),
('Lab-1', 'Block B', 30, 'lab'),
('Lab-2', 'Block B', 30, 'lab'),
('201', 'Block C', 80, 'lecture'),
('202', 'Block C', 80, 'lecture');

-- Faculty
INSERT INTO faculty (name, email, phone, department_id, specialization, max_hours_per_week) VALUES
('Dr. Anitha Krishnan', 'anitha@college.edu', '9876543210', 1, 'Data Structures & Algorithms', 18),
('Prof. Ramesh Kumar', 'ramesh@college.edu', '9876543211', 1, 'Database Management Systems', 20),
('Dr. Priya Sharma', 'priya@college.edu', '9876543212', 1, 'Operating Systems', 16),
('Prof. Suresh Babu', 'suresh@college.edu', '9876543213', 2, 'Digital Electronics', 20),
('Dr. Meena Devi', 'meena@college.edu', '9876543214', 2, 'Signal Processing', 18),
('Prof. Karthik Raj', 'karthik@college.edu', '9876543215', 1, 'Computer Networks', 20),
('Dr. Lakshmi Nair', 'lakshmi@college.edu', '9876543216', 4, 'Web Technologies', 16),
('Prof. Vijay Anand', 'vijay@college.edu', '9876543217', 3, 'Thermodynamics', 20);

-- Subjects
INSERT INTO subjects (name, code, department_id, semester, credits, hours_per_week) VALUES
('Data Structures & Algorithms', 'CS301', 1, 3, 4, 4),
('Database Management Systems', 'CS302', 1, 3, 3, 3),
('Operating Systems', 'CS303', 1, 3, 3, 3),
('Computer Networks', 'CS401', 1, 4, 4, 4),
('Web Technologies', 'CS402', 1, 4, 3, 3),
('Digital Electronics', 'EC301', 2, 3, 4, 4),
('Signal Processing', 'EC302', 2, 3, 3, 3),
('Thermodynamics', 'ME301', 3, 3, 4, 4);

-- Allocations
INSERT INTO allocations (faculty_id, subject_id, semester, academic_year) VALUES
(1, 1, 3, '2025-2026'),
(2, 2, 3, '2025-2026'),
(3, 3, 3, '2025-2026'),
(6, 4, 4, '2025-2026'),
(7, 5, 4, '2025-2026'),
(4, 6, 3, '2025-2026'),
(5, 7, 3, '2025-2026'),
(8, 8, 3, '2025-2026');

-- Timetable entries (CSE Sem 3)
INSERT INTO timetables (department_id, semester, academic_year, day_of_week, time_slot_id, subject_id, faculty_id, classroom_id, class_section) VALUES
(1, 3, '2025-2026', 'Monday', 1, 1, 1, 1, 'A'),
(1, 3, '2025-2026', 'Monday', 2, 2, 2, 1, 'A'),
(1, 3, '2025-2026', 'Monday', 3, 3, 3, 1, 'A'),
(1, 3, '2025-2026', 'Tuesday', 1, 2, 2, 2, 'A'),
(1, 3, '2025-2026', 'Tuesday', 2, 1, 1, 2, 'A'),
(1, 3, '2025-2026', 'Wednesday', 1, 3, 3, 1, 'A'),
(1, 3, '2025-2026', 'Wednesday', 3, 1, 1, 4, 'A'),
(1, 3, '2025-2026', 'Thursday', 2, 2, 2, 1, 'A'),
(1, 3, '2025-2026', 'Thursday', 4, 3, 3, 2, 'A'),
(1, 3, '2025-2026', 'Friday', 1, 1, 1, 1, 'A'),
(1, 3, '2025-2026', 'Friday', 3, 2, 2, 3, 'A');

-- Admin user (password: admin123)
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
