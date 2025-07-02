<?php
require_once __DIR__ . '/dbConfig.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Step 0: Get list of active user IDs (staff only)
$activeUserIds = [];
$usersQuery = "SELECT id FROM users WHERE status = 'active' AND user_type = 'staff'";
$usersResult = mysqli_query($conn, $usersQuery);
if ($usersResult) {
    while ($user = mysqli_fetch_assoc($usersResult)) {
        $activeUserIds[] = $user['id'];
    }
}

// Prepare CSV file
$csvFile = fopen('tasks_export.csv', 'w');
// Add CSV header with Counter
fputcsv($csvFile, ['S.No', 'Task ID', 'Task Name', 'Assigned To', 'Collaborators']);

// Start HTML table
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr>
        <th>S.No</th>
        <th>Task ID</th>
        <th>Task Name</th>
        <th>Assigned To</th>
        <th>Collaborators</th>
      </tr>";

// Step 1: Query to fetch task data (excluding done)

// $sql = "SELECT id AS Task_id, title AS Task_Name, assigned_to, project_id, collaborators FROM tasks WHERE status_id IN (1,2)";

$sql = "SELECT id AS Task_id, title AS Task_Name, assigned_to, project_id, collaborators FROM tasks WHERE status_id IN (1, 2)
      AND DATE(created_date) IN (CURDATE(), CURDATE() - INTERVAL 1 DAY)";


$result = mysqli_query($conn, $sql);

$counter = 1;

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $taskId = $row['Task_id'];
        $taskName = $row['Task_Name'].'-'.$taskId ;
        $assignedTo = $row['assigned_to'];
        $creatorId = $row['collaborators'];

        // ✅ Skip if both creator and assigned_to are not in active users list
        if (!in_array($creatorId, $activeUserIds) && !in_array($assignedTo, $activeUserIds)) {
            continue;
           echo $taskId;
        }

        // ✅ If creator is missing or inactive, fallback to assigned_to
        if (empty($creatorId) || !in_array($creatorId, $activeUserIds)) {
            $creatorId = $assignedTo;
        }

        // ✅ Write to CSV with counter
        fputcsv($csvFile, [$counter, $taskId, $taskName, $assignedTo, $creatorId]);

        // ✅ Print in HTML table with counter
        echo "<tr>
                <td>{$counter}</td>
                <td>{$taskId}</td>
                <td>{$taskName}</td>
                <td>{$assignedTo}</td>
                <td>{$creatorId}</td>
              </tr>";

        $counter++;
    }
    fclose($csvFile);
    echo "</table>";
    echo "<pre>✅ CSV export completed. File: tasks_export.csv</pre>";
} else {
    echo "⚠️ No records found or query failed.";
}
