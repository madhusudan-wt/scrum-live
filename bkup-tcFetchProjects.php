<?php
require_once __DIR__ . '/dbConfig.php';


// $sql = "SELECT p.id, p.title, p.project_type, pm.user_id AS member_id, pm.project_id FROM projects p JOIN project_members pm
//         ON p.id = pm.project_id LIMIT 500";

// $sql = "SELECT p.id AS project_id ,p.title,p.project_type,GROUP_CONCAT(pm.user_id) AS member_ids FROM projects p JOIN project_members pm 
//         ON p.id = pm.project_id GROUP BY p.id LIMIT 500";

$sql = "SELECT p.id, p.title, p.project_type, GROUP_CONCAT(pm.user_id) AS member_ids, GROUP_CONCAT(CONCAT(u.first_name)) AS members FROM projects p JOIN project_members pm ON p.id = pm.project_id JOIN users u ON pm.user_id = u.id WHERE u.status = 'active' AND u.user_type = 'staff' GROUP BY p.id LIMIT 500";

$result = mysqli_query($conn, $sql);

echo "<pre>";
if ($result && mysqli_num_rows($result) > 0) {
    // Start table
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";

    // Fetch the first row to create table headers
    $firstRow = mysqli_fetch_assoc($result);
    echo "<tr>";
    foreach ($firstRow as $column => $value) {
        echo "<th>" . htmlspecialchars($column) . "</th>";
    }
    echo "</tr>";

    // Output first row data
    echo "<tr>";
    foreach ($firstRow as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";

    // Output remaining rows
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "⚠️ No records found or query failed.";
}

