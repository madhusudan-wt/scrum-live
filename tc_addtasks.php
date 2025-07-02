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

// Step 1: Query to fetch task data (excluding done)
// $sql = "SELECT id, title, project_id, assigned_to, created_by FROM tasks WHERE status_id == 1  OR status_id == 2 ";
$sql = "SELECT id, title, project_id, assigned_to, created_by FROM tasks WHERE status_id = 1 OR status_id = 2";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $taskId = $row['id'];
        $taskName = $row['title'] . '-' . $taskId;
        $assignedTo = $row['assigned_to'];
        $creatorId = $row['created_by'];
        $projectId = $row['project_id'];

        // ✅ Skip if both creator and assigned_to are not in active users list
        if (!in_array($creatorId, $activeUserIds) && !in_array($assignedTo, $activeUserIds)) {
            echo "<pre>⛔ Task ID {$taskId} skipped: neither creator({$creatorId}) nor assigned user({$assignedTo}) is active staff.\n</pre>";
            continue;
        }

        // ✅ If creator is missing or inactive, fallback to assigned_to
        if (empty($creatorId) || !in_array($creatorId, $activeUserIds)) {
            $creatorId = $assignedTo;
        }

        // Fetch project name from projects table using project_id
        $projectName = '';
        $projectQuery = "SELECT title FROM projects WHERE id = " . (int)$projectId;
        $projectResult = mysqli_query($conn, $projectQuery);
        if ($projectResult && mysqli_num_rows($projectResult) > 0) {
            $projectRow = mysqli_fetch_assoc($projectResult);
            $projectName = $projectRow['title'];
        } else {
            echo "<pre>⚠️ Project name not found for Project ID: $projectId\n</pre>";
            continue;
        }

        // Prepare API data
        $postData = [
            "projectName" => $projectName . '-' . $projectId,
            "projectResponsiblePersonEmployeeId" => $creatorId,
            "tasks" => [
                [
                    "goalName" => $taskName,
                    "taskName" => $taskName,
                    "taskResponsiblePersonEmployeeId" => $assignedTo
                ]
            ]
        ];

        // Setup cURL
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://webiators.v3.timechamp.io/swagger/api/tasks');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1laWQiOiIyY2ZkMzdjOS0zODE3LTRkYmQtODI4ZC1hYTY3MWFlY2E0YTMiLCJDb21wYW55IjoiMGY5ZTU1MjYtMDE0Mi00ZWQ2LWE4YmEtZjYwNWY5MGVhNGE0IiwibmJmIjoxNzEwODYzNTE5LCJleHAiOjIxNDI4NjM1MTksImlhdCI6MTcxMDg2MzUxOX0.d-YTpHdW4t_HWSuwUHX-Fm3s5LMZfX0IzwlQ8dKnxg8'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        // Execute cURL and capture response sssssssss
        $response = curl_exec($ch);

        echo "<pre>";
        if (curl_errno($ch)) {
            echo "❌ cURL error for Task ID {$taskId}: " . curl_error($ch) . "\n";
        } else {
            echo "✅ Response for Task ID {$taskId}: $response\n";
        }
        echo "</pre>";

        curl_close($ch);
    }
} else {
    echo "⚠️ No records found or query failed.";
}
