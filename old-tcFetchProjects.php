<?php
require_once __DIR__ . '/dbConfig.php';

// TimeChamp API endpoint
$apiUrl = "https://webiators.v3.timechamp.io/swagger/api/tasks";

// Optional: replace this if the API requires auth
$accessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1laWQiOiIyY2ZkMzdjOS0zODE3LTRkYmQtODI4ZC1hYTY3MWFlY2E0YTMiLCJDb21wYW55IjoiMGY5ZTU1MjYtMDE0Mi00ZWQ2LWE4YmEtZjYwNWY5MGVhNGE0IiwibmJmIjoxNzEwODYzNTE5LCJleHAiOjIxNDI4NjM1MTksImlhdCI6MTcxMDg2MzUxOX0.d-YTpHdW4t_HWSuwUHX-Fm3s5LMZfX0IzwlQ8dKnxg8';
exit('added exit in code');
// SQL to fetch one project with members
$sql = "SELECT p.id, p.title, p.project_type, GROUP_CONCAT(pm.user_id) AS member_ids, GROUP_CONCAT(CONCAT(u.first_name)) AS members 
        FROM projects p 
        JOIN project_members pm ON p.id = pm.project_id 
        JOIN users u ON pm.user_id = u.id 
        WHERE u.status = 'active' AND u.user_type = 'staff' 
        GROUP BY p.id"; 

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {

    $projectName = $row['title'];

    // Get first member ID from comma-separated list
    $memberIds = explode(',', $row['member_ids']);
    $firstMemberId = trim($memberIds[0]);

    $taskTitle = "Task for project: " . $projectName;

    // JSON data to send
    $postData = [
        "projectName" => $projectName,
        "projectResponsiblePersonEmployeeId" => $firstMemberId,
        "tasks" => [
            [
                "goalName" => $taskTitle,
                "taskName" => $taskTitle,
                "taskResponsiblePersonEmployeeId" => $firstMemberId
            ]
        ]
    ];

    // Setup cURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    // Set headers
    $headers = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($postData))
    ];
    if (!empty($accessToken)) {
        $headers[] = 'Authorization: ' . $accessToken;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    // Execute and capture response
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Output response
    echo "<pre>HTTP Status Code: $httpCode\n";
    echo "API Response:\n";
    print_r(json_decode($response, true));
}
} else {
    echo "⚠️ No valid data found from the database.";
}
?>
