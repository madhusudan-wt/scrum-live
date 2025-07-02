    <?php
    require_once __DIR__ . "/../dbConfig.php";

    $token =
        "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1laWQiOiIyY2ZkMzdjOS0zODE3LTRkYmQtODI4ZC1hYTY3MWFlY2E0YTMiLCJDb21wYW55IjoiMGY5ZTU1MjYtMDE0Mi00ZWQ2LWE4YmEtZjYwNWY5MGVhNGE0IiwibmJmIjoxNzEwODYzNTE5LCJleHAiOjIxNDI4NjM1MTksImlhdCI6MTcxMDg2MzUxOX0.d-YTpHdW4t_HWSuwUHX-Fm3s5LMZfX0IzwlQ8dKnxg8";
    $apiBase = "https://webiators.v3.timechamp.io/swagger/api/activity/worklog";

    $ist = new DateTimeZone("Asia/Kolkata");
    // ========= Live code for last day data sync ==========
    // Use Asia/Kolkata for calculation only (don't change server timezone)

    // Get previous day's 00:00:00 IST
    $dateFrom = new DateTime("yesterday 00:00:00", $ist);

    // Get previous day's 23:59:59 IST
    $dateTo = new DateTime("yesterday 23:59:59", $ist);

    // Format in ISO8601 with milliseconds (e.g., 2025-06-10T00:00:00.000)
    $dateFromEncoded = rawurlencode($dateFrom->format("Y-m-d\TH:i:s.000"));
    $dateToEncoded = rawurlencode($dateTo->format("Y-m-d\TH:i:s.000"));

    // =============================end============================

    // ===================Test code to sync previous 24 hours data =========== DO NOT DELETE IT

    // $nowIST = new DateTime('now', $ist);

    // // Get time 24 hours ago in IST
    // $pastIST = clone $nowIST;
    // $pastIST->modify('-24 hours');

    // // Format in ISO8601 with milliseconds (e.g., 2025-06-24T03:00:00.000)
    // $dateFromEncoded = rawurlencode($pastIST->format('Y-m-d\TH:i:s.000'));
    // $dateToEncoded = rawurlencode($nowIST->format('Y-m-d\TH:i:s.000'));

    // ===============================================================

    // Step 1: Fetch tasks
    $sql =
        "SELECT * FROM tasks WHERE status = 'to_do' OR status = 'in_progress'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($task = $result->fetch_assoc()) {
            $taskId = $task["id"];
            $taskTitle = $task["title"] . "-" . $taskId;
            $assignedTo = $task["assigned_to"];
            $projectId = $task["project_id"];

            // Get project name
            $projectResult = $conn->query(
                "SELECT title FROM projects WHERE id = $projectId"
            );
            $project = $projectResult ? $projectResult->fetch_assoc() : null;
            $projectName = $project ? $project["title"] : null;

            if (!$projectName) {
                echo "Skipping task ID $taskId: Project not found.<br>";
                continue;
            }

            // Build API URL
            $url =
                "$apiBase?ProjectName=" .
                rawurlencode($projectName) .
                "&TaskName=" .
                rawurlencode($taskTitle) .
                "&EmployeeId=$assignedTo" .
                "&DateFrom=$dateFromEncoded&DateTo=$dateToEncoded";

            // Call TimeChamp API
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "accept: application/json",
                "Authorization: Bearer $token",
            ]);
            $apiResponse = curl_exec($ch);
            curl_close($ch);

            // Debug output
            $data = json_decode($apiResponse, true);

            if (
                json_last_error() === JSON_ERROR_NONE &&
                !empty($data["data"])
            ) {
                foreach ($data["data"] as $entry) {
                    $personWorkedOntask = $entry["workLoggedPersonEmployeeId"];
                    preg_match('/-(\d+)$/', $entry["taskName"], $matches);
                    $extractedTaskId = $matches[1] ?? null;

                    if (!$extractedTaskId) {
                        echo "❌ Task ID not found in taskName: {$entry["taskName"]}<br>";
                        continue;
                    }
                    $startTime = substr($entry["userTaskStartTime"], 0, 19);
                    $endTime = substr($entry["userTaskEndTime"], 0, 19);
                    $timeSpentMin = (int) $entry["timeSpentOnTaskInMin"];

                    // Convert to GMT (UTC)

                    $start = new DateTime(
                        $startTime,
                        new DateTimeZone("Asia/Kolkata")
                    );
                    $end = new DateTime(
                        $endTime,
                        new DateTimeZone("Asia/Kolkata")
                    );

                    // Convert to GMT (UTC)

                    $serverTimezone = date_default_timezone_get(); 
                    // echo "time zone server : " . $serverTimezone;
                    $start->setTimezone(new DateTimeZone($serverTimezone));
                    $end->setTimezone(new DateTimeZone($serverTimezone));

                    // Output in desired format (ISO or Y-m-d H:i:s)
                    $gmtStartTime = $start->format("Y-m-d H:i:s");
                    $gmtEndTime = $end->format("Y-m-d H:i:s");

                    $hours = 0;

                    $stmt = $conn->prepare(
                        "INSERT IGNORE INTO project_time (task_id, project_id, user_id, start_time, end_time, hours) VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "iiissd",
                        $extractedTaskId,
                        $projectId,
                        $personWorkedOntask,
                        $gmtStartTime,
                        $gmtEndTime,
                        $hours
                    );

                    if ($stmt->execute()) {
                        echo "✅ Inserted log: Task ID $extractedTaskId — $hours hours ($startTime to $endTime)<br>";
                    } else {
                        echo "❌ Failed to insert log for task ID $extractedTaskId<br>";
                    }
                }
            } else {
                echo "Invalid or empty API response for task ID $taskId<br>";
            }
        }
    } else {
        echo "No matching tasks found.";
    }

    $conn->close();

