<?php
// Database setup script for Easeked Queue
// Run this file once to create the database and tables

// Database configuration - matches config.php
$servername = "localhost:3306";
$username = "root"; // Default XAMPP MySQL username
$password = ""; // Default XAMPP MySQL password (empty)

// Create connection without specifying database
// Note: This connects to MySQL server, not the specific database
error_log("Attempting to connect to MySQL with server: $servername, user: $username");
echo "Attempting to connect to MySQL with server: $servername, user: $username, password: " . (empty($password) ? 'empty' : 'set') . "<br>";
$conn = new mysqli($servername, $username, $password);

// Debug: Check if connection is successful
if ($conn->connect_error) {
    echo "Connection error details: " . $conn->connect_error . "<br>";
    echo "Error code: " . $conn->connect_errno . "<br>";
    die("Connection failed: " . $conn->connect_error . "<br>Make sure MySQL is running in XAMPP.");
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "<br>Make sure MySQL is running in XAMPP.");
}

echo "Connected to MySQL successfully.<br>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS easeked_queue";
if ($conn->query($sql) === TRUE) {
    echo "Database 'easeked_queue' created successfully.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db("easeked_queue");

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    role ENUM('citizen', 'admin', 'super_admin') NOT NULL DEFAULT 'citizen',
    department_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully.<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create departments table
$sql = "CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_class VARCHAR(100)
)";

if ($conn->query($sql) === TRUE) {
    echo "Departments table created successfully.<br>";
} else {
    echo "Error creating departments table: " . $conn->error . "<br>";
}

// Create services table
$sql = "CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Services table created successfully.<br>";
} else {
    echo "Error creating services table: " . $conn->error . "<br>";
}

// Create appointments table
$sql = "CREATE TABLE IF NOT EXISTS appointments (
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
    status ENUM('pending', 'confirmed', 'declined', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    uploaded_file_path VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Appointments table created successfully.<br>";
} else {
    echo "Error creating appointments table: " . $conn->error . "<br>";
}

// Insert sample departments
$sql = "INSERT IGNORE INTO departments (name, description, icon_class) VALUES
('Mayor\'s Office / BPLO', 'Business permits, clearances, official requests, appointments with the Mayor', 'fas fa-building'),
('Municipal Health Office', 'Medical consultations, immunization, maternal/child health, wellness programs', 'fas fa-heartbeat'),
('Civil Registry', 'Birth registration, marriage registration, death registration, issuance of certificates', 'fas fa-file-contract'),
('Treasurer\'s Office', 'Tax collection, permit fees, real property tax payments', 'fas fa-coins'),
('Assessor\'s Office', 'Real property assessment, certifications', 'fas fa-home'),
('Social Welfare & Development Office (MSWDO)', 'Assistance for indigents, PWD assistance, senior citizen assistance, relief distribution', 'fas fa-hands-helping'),
('Engineering Office', 'Building permits, construction inspections, project requests', 'fas fa-tools'),
('Agriculture Office', 'Farmer assistance, farm inputs, livelihood programs', 'fas fa-seedling'),
('Sangguniang Bayan Secretary', 'Resolutions, ordinances, legislative documents', 'fas fa-gavel')";

if ($conn->query($sql) === TRUE) {
    echo "Sample departments inserted successfully.<br>";
} else {
    echo "Error inserting departments: " . $conn->error . "<br>";
}

// Insert sample services
$sql = "INSERT IGNORE INTO services (department_id, service_name) VALUES
(1, 'Business permits'),
(1, 'Clearances'),
(1, 'Official requests'),
(1, 'Appointments with the Mayor'),
(2, 'Medical consultations'),
(2, 'Immunization'),
(2, 'Maternal/child health'),
(2, 'Wellness programs'),
(3, 'Birth registration'),
(3, 'Marriage registration'),
(3, 'Death registration'),
(3, 'Issuance of certificates'),
(4, 'Tax collection'),
(4, 'Permit fees'),
(4, 'Real property tax payments'),
(5, 'Real property assessment'),
(5, 'Certifications'),
(6, 'Assistance for indigents'),
(6, 'PWD assistance'),
(6, 'Senior citizen assistance'),
(6, 'Relief distribution'),
(7, 'Building permits'),
(7, 'Construction inspections'),
(7, 'Project requests'),
(8, 'Farmer assistance'),
(8, 'Farm inputs'),
(8, 'Livelihood programs'),
(9, 'Resolutions'),
(9, 'Ordinances'),
(9, 'Legislative documents')";

if ($conn->query($sql) === TRUE) {
    echo "Sample services inserted successfully.<br>";
} else {
    echo "Error inserting services: " . $conn->error . "<br>";
}

// Create uploads directory
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo "Uploads directory created successfully.<br>";
    } else {
        echo "Error creating uploads directory.<br>";
    }
} else {
    echo "Uploads directory already exists.<br>";
}

$conn->close();

echo "<br><strong>Database setup completed!</strong><br>";
echo "You can now access the Easeked Queue system.<br>";
echo "<a href='index.php'>Go to Home Page</a>";
?>