<?php
// This API endpoint returns valid appointment dates excluding weekends and Philippine holidays
header('Content-Type: application/json');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['month']) || !isset($input['year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$month = intval($input['month']);
$year = intval($input['year']);

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2024 || $year > 2050) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid month or year']);
    exit;
}

// Philippine Holidays 2024-2026
// Format: array of dates as 'YYYY-MM-DD' and moveable holidays
$philippines_holidays = [
    // Fixed Holidays
    '2024-01-01', // New Year's Day
    '2024-02-10', // EDSA Revolution Anniversary
    '2024-02-12', // Chinese New Year
    '2024-02-13', // Chinese New Year (Holiday)
    '2024-03-28', // Maundy Thursday
    '2024-03-29', // Good Friday
    '2024-03-30', // Black Saturday
    '2024-04-09', // Day of Valor
    '2024-04-10', // Araw ng Kagitingan
    '2024-06-12', // Independence Day
    '2024-06-17', // Eid'l Fitr
    '2024-08-21', // Ninoy Aquino Day
    '2024-08-26', // National Heroes Day
    '2024-11-01', // All Saints' Day
    '2024-11-30', // Bonifacio Day
    '2024-12-08', // Feast of the Immaculate Conception
    '2024-12-25', // Christmas Day
    '2024-12-30', // Rizal Day
    '2024-12-31', // New Year's Eve (Special Non-Working Day)
    
    // 2025 Holidays
    '2025-01-01', // New Year's Day
    '2025-01-25', // Chinese New Year
    '2025-01-27', // Chinese New Year (Holiday)
    '2025-02-10', // EDSA Revolution Anniversary
    '2025-04-09', // Araw ng Kagitingan
    '2025-04-17', // Maundy Thursday
    '2025-04-18', // Good Friday
    '2025-04-19', // Black Saturday
    '2025-04-10', // Day of Valor
    '2025-06-12', // Independence Day
    '2025-06-16', // Eid'l Fitr
    '2025-08-21', // Ninoy Aquino Day
    '2025-08-25', // National Heroes Day
    '2025-11-01', // All Saints' Day
    '2025-11-30', // Bonifacio Day
    '2025-12-08', // Feast of the Immaculate Conception
    '2025-12-25', // Christmas Day
    '2025-12-30', // Rizal Day
    
    // 2026 Holidays
    '2026-01-01', // New Year's Day
    '2026-02-10', // EDSA Revolution Anniversary
    '2026-02-11', // Chinese New Year
    '2026-02-12', // Chinese New Year (Holiday)
    '2026-04-09', // Araw ng Kagitingan
    '2026-04-02', // Maundy Thursday
    '2026-04-03', // Good Friday
    '2026-04-04', // Black Saturday
    '2026-04-10', // Day of Valor
    '2026-06-12', // Independence Day
    '2026-07-06', // Eid'l Fitr
    '2026-08-21', // Ninoy Aquino Day
    '2026-08-31', // National Heroes Day
    '2026-11-01', // All Saints' Day
    '2026-11-30', // Bonifacio Day
    '2026-12-08', // Feast of the Immaculate Conception
    '2026-12-25', // Christmas Day
    '2026-12-30', // Rizal Day
];

// Get the first day of the month and calculate grid
$first_day = strtotime("$year-$month-01");
$last_day = strtotime("$year-$month-" . date('t', $first_day));
$days_in_month = date('t', $first_day);
$start_weekday = date('w', $first_day); // 0 = Sunday, 6 = Saturday

// Generate calendar data
$calendar_dates = [];
$available_dates = [];

// Add previous month's trailing days (grayed out)
if ($start_weekday > 0) {
    $prev_month_last_day = date('t', strtotime("$year-$month-01 -1 day"));
    $prev_month_start = $prev_month_last_day - $start_weekday + 1;
    for ($i = $prev_month_start; $i <= $prev_month_last_day; $i++) {
        $calendar_dates[] = [
            'date' => null,
            'day' => $i,
            'is_current_month' => false
        ];
    }
}

// Current month's dates
for ($day = 1; $day <= $days_in_month; $day++) {
    $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
    $date_timestamp = strtotime($date_str);
    $weekday = date('w', $date_timestamp); // 0 = Sunday, 6 = Saturday
    $is_weekend = ($weekday == 0 || $weekday == 6);
    $is_holiday = in_array($date_str, $philippines_holidays);
    $is_past = ($date_timestamp < strtotime(date('Y-m-d')));
    
    $is_available = !$is_weekend && !$is_holiday && !$is_past;
    
    $calendar_dates[] = [
        'date' => $date_str,
        'day' => $day,
        'is_current_month' => true,
        'is_weekend' => $is_weekend,
        'is_holiday' => $is_holiday,
        'is_past' => $is_past,
        'is_available' => $is_available,
        'weekday' => $weekday
    ];
    
    if ($is_available) {
        $available_dates[] = $date_str;
    }
}

// Add next month's leading days (grayed out)
$total_cells = count($calendar_dates);
$cells_needed = 42; // 6 weeks × 7 days
$next_month_days = $cells_needed - $total_cells;
for ($i = 1; $i <= $next_month_days; $i++) {
    $calendar_dates[] = [
        'date' => null,
        'day' => $i,
        'is_current_month' => false
    ];
}

// Get appointment count for display purposes
$conn = getDBConnection();
$dept_id = $_SESSION['booking_department_id'] ?? null;
$booked_dates = [];

if ($conn && $dept_id) {
    // Get dates with fully booked appointments (all 6 slots taken)
    $stmt = $conn->prepare("
        SELECT appointment_date, COUNT(*) as count 
        FROM appointments 
        WHERE YEAR(appointment_date) = ? 
        AND MONTH(appointment_date) = ? 
        AND department_id = ? 
        AND status IN ('confirmed', 'pending')
        GROUP BY appointment_date
        HAVING count >= 6
    ");
    
    if ($stmt) {
        $stmt->bind_param("iii", $year, $month, $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $booked_dates[] = $row['appointment_date'];
        }
        $stmt->close();
    }
    
    $conn->close();
}

// Mark fully booked available dates
foreach ($calendar_dates as &$date_info) {
    if ($date_info['is_current_month'] && $date_info['is_available'] && in_array($date_info['date'], $booked_dates)) {
        $date_info['is_fully_booked'] = true;
        $date_info['is_available'] = false;
    }
}

echo json_encode([
    'success' => true,
    'month' => $month,
    'year' => $year,
    'month_name' => date('F', $first_day),
    'calendar_dates' => $calendar_dates,
    'available_dates' => $available_dates,
    'holidays' => [
        'weekends' => 'Saturdays and Sundays',
        'count' => count($philippines_holidays),
        'note' => 'Philippine National Holidays'
    ]
]);
?>
