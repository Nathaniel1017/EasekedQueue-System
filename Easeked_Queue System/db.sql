-- Easeked Queue Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS easeked_queue;
USE easeked_queue;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    role ENUM('citizen', 'admin', 'super_admin') NOT NULL DEFAULT 'citizen',
    department_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_class VARCHAR(100)
);

-- Services table
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Appointments table
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    service_id INT NULL,
    appointment_for ENUM('me', 'someone_else') NOT NULL,
    recipient_name VARCHAR(255),
    recipient_phone VARCHAR(20),
    recipient_email VARCHAR(255),
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    agenda TEXT,
    custom_agenda TEXT NULL,
    status ENUM('pending', 'confirmed', 'declined', 'completed', 'cancelled', 'unattended') NOT NULL DEFAULT 'pending',
    decline_reason TEXT NULL,
    uploaded_file_path VARCHAR(255) NULL,
    is_pwd BOOLEAN DEFAULT 0,
    pwd_proof VARCHAR(255) NULL,
    priority_status ENUM('normal', 'priority') DEFAULT 'normal',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- Insert sample departments
INSERT INTO departments (name, description, icon_class) VALUES
('Mayor\'s Office / BPLO', 'Business permits, clearances, official requests, appointments with the Mayor', 'fas fa-building'),
('Municipal Health Office', 'Medical consultations, immunization, maternal/child health, wellness programs', 'fas fa-heartbeat'),
('Civil Registry', 'Birth registration, marriage registration, death registration, issuance of certificates', 'fas fa-file-contract'),
('Treasurer\'s Office', 'Tax collection, permit fees, real property tax payments', 'fas fa-coins'),
('Assessor\'s Office', 'Real property assessment, certifications', 'fas fa-home'),
('Social Welfare & Development Office (MSWDO)', 'Assistance for indigents, PWD assistance, senior citizen assistance, relief distribution', 'fas fa-hands-helping'),
('Engineering Office', 'Building permits, construction inspections, project requests', 'fas fa-tools'),
('Agriculture Office', 'Farmer assistance, farm inputs, livelihood programs', 'fas fa-seedling'),
('Sangguniang Bayan Secretary', 'Resolutions, ordinances, legislative documents', 'fas fa-gavel');

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    for_admin_id INT NULL,
    message TEXT NOT NULL,
    appointment_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (for_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);