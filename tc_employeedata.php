<?php

require_once __DIR__ . '/dbConfig.php';



// Query
$sql = "SELECT id, first_name, last_name, role_id FROM users WHERE status='active' AND user_type='staff'";
$result = $conn->query($sql);

// Display results in HTML table
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='8' cellspacing='0'>";
    echo "<tr>
            <th>S No.</th>
            <th>User ID</th>
            <th>Name</th>
            
          </tr>";
    $serial = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $serial++ . "</td>
                <td>" . htmlspecialchars($row['id']) . "</td>
                <td>" . htmlspecialchars($row['first_name']).' ' .htmlspecialchars($row['last_name']). "</td>
                
              </tr>";
    }

    echo "</table>";
} else {
    echo "No users found.";
}

$conn->close();




// name or id table format