<?php
/**
 * Script to Remove Duplicate Departments
 * Keeps the row with the smallest ID for each department name
 */

require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Remove Duplicate Departments</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
</head>
<body>
<div class='container mt-5'>
<h2><i class='fas fa-database'></i> Remove Duplicate Departments</h2>
<hr>";

// Step 1: Get initial count
$result = $conn->query("SELECT COUNT(*) as count FROM departments");
$initial_count = $result->fetch_assoc()['count'];
echo "<p><strong>Initial department count:</strong> $initial_count</p>";

// Step 2: Find duplicates
echo "<h4>Finding Duplicate Departments:</h4>";
$result = $conn->query("
    SELECT name, COUNT(*) as duplicate_count, GROUP_CONCAT(id ORDER BY id) as ids
    FROM departments
    GROUP BY name
    HAVING COUNT(*) > 1
    ORDER BY duplicate_count DESC
");

$duplicates_found = $result->num_rows;

if ($duplicates_found == 0) {
    echo "<p><em style='color: blue;'>✓ No duplicates found. Database is clean.</em></p>";
} else {
    echo "<p><strong>Found $duplicates_found department(s) with duplicates:</strong></p>";
    echo "<table class='table table-striped'>";
    echo "<thead><tr><th>Department Name</th><th>Count</th><th>IDs (keeping lowest)</th></tr></thead>";
    echo "<tbody>";
    
    while ($row = $result->fetch_assoc()) {
        $ids_array = explode(',', $row['ids']);
        $keeping_id = $ids_array[0]; // Smallest ID
        $removing_ids = implode(', ', array_slice($ids_array, 1));
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . $row['duplicate_count'] . "</td>";
        echo "<td>Keeping ID: <strong>$keeping_id</strong><br/>Removing IDs: $removing_ids</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
}

// Step 3: Execute the DELETE query to remove duplicates
echo "<h4>Removing Duplicates:</h4>";

if ($duplicates_found > 0) {
    // Use a DELETE with subquery to keep only the row with the smallest ID for each name
    $delete_query = "
        DELETE FROM departments
        WHERE id NOT IN (
            SELECT min_id FROM (
                SELECT MIN(id) as min_id FROM departments GROUP BY name
            ) AS subquery
        )
    ";
    
    if ($conn->query($delete_query)) {
        $rows_deleted = $conn->affected_rows;
        echo "<p style='color: green;'><strong>✓ Successfully removed $rows_deleted duplicate record(s)</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Error removing duplicates: " . $conn->error . "</strong></p>";
    }
} else {
    echo "<p style='color: blue;'><em>No duplicates to remove.</em></p>";
}

// Step 4: Get final count and verify
$result = $conn->query("SELECT COUNT(*) as count FROM departments");
$final_count = $result->fetch_assoc()['count'];

echo "<h4>Final Result:</h4>";
echo "<p><strong>Final department count:</strong> $final_count</p>";
echo "<p><strong>Duplicates removed:</strong> " . ($initial_count - $final_count) . "</p>";

// Step 5: Show remaining departments
echo "<h4>Departments After Cleanup:</h4>";
$result = $conn->query("SELECT id, name FROM departments ORDER BY id");
echo "<table class='table table-striped'>";
echo "<thead><tr><th>ID</th><th>Name</th></tr></thead>";
echo "<tbody>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>#" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";

$conn->close();

echo "<hr>";
if ($initial_count - $final_count > 0) {
    echo "<p style='color: green;'><i class='fas fa-check-circle'></i> <strong>Database cleanup completed successfully!</strong></p>";
} else {
    echo "<p style='color: blue;'><i class='fas fa-info-circle'></i> <strong>Database is clean. No duplicates found.</strong></p>";
}
echo "<p><a href='admin_dashboard.php' class='btn btn-primary'>Back to Admin Dashboard</a></p>";
echo "</div>
</body>
</html>";
?>
