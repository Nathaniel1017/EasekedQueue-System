<?php
// This API endpoint returns available time slots for a specific date and department with real-time status
header('Content-Type: application/json');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['date']) || !isset($input['department_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$date = $input['date'];
$department_id = intval($input['department_id']);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Define all department slots (8-11 AM and 1-4 PM)
$all_slots = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$slot_details = [];
$available_count = 0;
$booked_count = 0;

// Check each slot for real-time availability
foreach ($all_slots as $slot) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND department_id = ? AND status IN ('confirmed', 'pending')");
    
    if ($stmt) {
        $stmt->bind_param("ssi", $date, $slot, $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $booked = $row['count'] > 0;
        
        $slot_details[] = [
            'time' => $slot,
            'display_time' => date('g:i A', strtotime($slot)),
            'available' => !$booked,
            'booked' => $booked,
            'count' => $row['count']
        ];
        
        if ($booked) {
            $booked_count++;
        } else {
            $available_count++;
        }
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'date' => $date,
    'date_formatted' => date('l, M d, Y', strtotime($date)),
    'slots' => $slot_details,
    'available_slots' => array_column(array_filter($slot_details, function($s) { return $s['available']; }), 'time'),
    'booked_slots' => array_column(array_filter($slot_details, function($s) { return $s['booked']; }), 'time'),
    'total_slots' => count($all_slots),
    'available_count' => $available_count,
    'booked_count' => $booked_count,
    'is_fully_booked' => $available_count === 0
]);
?>
