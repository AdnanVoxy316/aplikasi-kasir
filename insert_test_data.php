<?php
require_once 'config/database.php';

echo "<h2>Inserting Test Attendance Data</h2>";

// Insert attendance logs for user_id = 1
$test_data = [
    ['2026-05-14 08:00:00', '2026-05-14 17:30:00'],
    ['2026-05-13 08:15:00', '2026-05-13 17:45:00'],
    ['2026-05-12 08:30:00', '2026-05-12 18:00:00'],
    ['2026-05-11 09:00:00', '2026-05-11 18:30:00'],
    ['2026-05-10 08:00:00', '2026-05-10 17:00:00'],
];

$stmt = $conn->prepare('INSERT INTO attendance_logs (user_id, clock_in_at, clock_out_at) VALUES (1, ?, ?)');

$inserted = 0;
foreach ($test_data as $row) {
    $stmt->bind_param('ss', $row[0], $row[1]);
    if ($stmt->execute()) {
        $inserted++;
        echo "<p>Inserted: " . $row[0] . " to " . $row[1] . "</p>";
    } else {
        echo "<p>Failed to insert row</p>";
    }
}

$stmt->close();

// Verify the data
echo "<h3>Verification - Total records for user_id=1:</h3>";
$result = $conn->query("SELECT COUNT(*) as total FROM attendance_logs WHERE user_id = 1");
$row = $result->fetch_assoc();
echo "<p>Total attendance_logs records: " . $row['total'] . "</p>";

echo "<h3>Recent records:</h3>";
$result = $conn->query("SELECT id, user_id, clock_in_at, clock_out_at FROM attendance_logs WHERE user_id = 1 ORDER BY id DESC LIMIT 5");
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>ID: " . $row['id'] . " | In: " . $row['clock_in_at'] . " | Out: " . ($row['clock_out_at'] ?? 'NULL') . "</li>";
}
echo "</ul>";

echo "<p><a href='settings.php#attendance'>Go to Settings Attendance Panel</a></p>";
$conn->close();
?>