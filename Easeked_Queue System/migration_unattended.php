<?php
// Migration script to add 'unattended' status to appointments table
require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed.");
}

$sql = "ALTER TABLE appointments MODIFY COLUMN status ENUM('pending', 'confirmed', 'declined', 'completed', 'cancelled', 'unattended') NOT NULL DEFAULT 'pending'";

if ($conn->query($sql) === TRUE) {
    echo "Database migration completed successfully! 'unattended' status added to appointments table.";
} else {
    echo "Error during migration: " . $conn->error;
}

$conn->close();
?>