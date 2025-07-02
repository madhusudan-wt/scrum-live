<?php
require_once __DIR__ . '/dbConfig.php';

// SQL to fetch one project with members
$sql = "SELECT p.id, p.title, p.project_type, GROUP_CONCAT(pm.user_id) AS member_ids, GROUP_CONCAT(CONCAT(u.first_name)) AS members 
        FROM projects p 
        JOIN project_members pm ON p.id = pm.project_id 
        JOIN users u ON pm.user_id = u.id 
        WHERE u.status = 'active' AND u.user_type = 'staff' 
        GROUP BY p.id"; 

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    // Prepare CSV file
    $csvFile = fopen('projects_data.csv', 'w');
    // Write headers to the CSV
    fputcsv($csvFile, ['Project ID', 'Project Title', 'Project Type', 'Members', 'Member IDs']);

    // Start the HTML table
    echo "<table border='1' cellpadding='10'>";
    echo "<thead><tr><th>Project ID</th><th>Project Title</th><th>Project Type</th><th>Members</th><th>Member IDs</th></tr></thead>";
    echo "<tbody>";

    while ($row = mysqli_fetch_assoc($result)) {
        $projectId = $row['id'];
        $projectName = $row['title'].'-'.$projectId;
        $projectType = $row['project_type'];
        $members = $row['members'];
        $memberIds = $row['member_ids'];

        // Write the row data to the CSV
        fputcsv($csvFile, [$projectId, $projectName, $projectType, $members, $memberIds]);

        // Display the data in the HTML table
        echo "<tr>";
        echo "<td>{$projectId}</td>";
        echo "<td>{$projectName}</td>";
        echo "<td>{$projectType}</td>";
        echo "<td>{$members}</td>";
        echo "<td>{$memberIds}</td>";
        echo "</tr>";
    }

    // End the HTML table
    echo "</tbody></table>";

    // Close the CSV file
    fclose($csvFile);

    echo "<p>Data has been saved to <a href='projects_data.csv' download>projects_data.csv</a></p>";
} else {
    echo "⚠️ No valid data found from the database.";
}
?>
