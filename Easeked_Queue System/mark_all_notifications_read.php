<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

try {
    $conn = getDBConnection();
    
    // Get all unread notification IDs for the user
    $stmt = $conn->prepare("
        SELECT n.id
        FROM notifications n
        WHERE n.user_id = ? AND n.is_read = 0
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode([
            'success' => true,
            'message' => 'All notifications are already marked as read.'
        ]);
        exit();
    }
    
    // Mark all unread notifications as read
    $update_stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ");
    
    if (!$update_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $update_stmt->bind_param("i", $user_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Execute failed: " . $update_stmt->error);
    }
    
    $update_stmt->close();
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'All notifications marked as read successfully.'
    ]);
    
} catch (Exception $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark notifications as read: ' . $e->getMessage()
    ]);
}
?>
