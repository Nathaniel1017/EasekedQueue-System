<?php
// Migration script to add notifications system
// Run this after setup_database.php or on existing database

require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

echo "Starting migration...<br>";

// Add decline_reason to appointments table
$sql = "ALTER TABLE appointments ADD COLUMN decline_reason TEXT NULL";
if ($conn->query($sql) === TRUE) {
    echo "Added decline_reason column to appointments table.<br>";
} else {
    echo "Error adding decline_reason column: " . $conn->error . "<br>";
}

// Create notifications table
$sql = "DROP TABLE IF EXISTS notifications";
if ($conn->query($sql) === TRUE) {
    echo "Dropped old notifications table.<br>";
} else {
    echo "Error dropping notifications table: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE notifications (
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
)";

if ($conn->query($sql) === TRUE) {
    echo "Notifications table created successfully.<br>";
} else {
    echo "Error creating notifications table: " . $conn->error . "<br>";
}

$conn->close();

echo "<br><strong>Migration completed!</strong><br>";
echo "The notification system is now ready.<br>";
?>