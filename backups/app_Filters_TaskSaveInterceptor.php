<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class TaskSaveInterceptor implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Nothing in before — only using after
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $post = $request->getPost();

        // Required: Must have task_id and project_id
        // Plus: At least one of title or user_id should be present
        $hasTaskId = !empty($post['task_id']);
        $hasProjectId = !empty($post['project_id']);
        $hasTitleOrUserId = !empty($post['title']) || !empty($post['user_id']);

        if (!($hasTaskId && $hasProjectId && $hasTitleOrUserId)) {
            return; // Skip logging if main conditions are not satisfied
        }

        $Id = $post['id'] ?? '-';
        $taskId = $post['task_id'] ?? '-';
        $creatorId = session()->get('user_id') ?? ($post['user_id'] ?? '-');
        $projectId = $post['project_id'] ?? '-';

        $db = \Config\Database::connect();

        // Get task title
        $taskName = '-';
        if (!empty($taskId)) {
            try {
                $builder = $db->table('tasks');
                $task = $builder->select('title, assigned_to')->where('id', (int)$taskId)->get()->getFirstRow('array');
                if (!empty($task['title'])) {
                    $taskName = $task['title'];
                }
                if (!empty($task['assigned_to'])) {
                    $assignedTo = $task['assigned_to'];
                }
            } catch (\Throwable $e) {
                $taskName = 'Error fetching task title: ' . $e->getMessage();
            }
        }

        // Get project title
        $projectName = '-';
        if (!empty($projectId)) {
            try {
                $builder = $db->table('projects');
                $project = $builder->select('title')->where('id', (int)$projectId)->get()->getFirstRow('array');
                if (!empty($project['title'])) {
                    $projectName = $project['title'];
                }
                

            } catch (\Throwable $e) {
                $projectName = 'Error fetching project name: ' . $e->getMessage();
            }
        }

        // Log only if all required fields are valid
        if (!empty($taskId) && !empty($taskName) && !empty($creatorId) && !empty($projectId) && !empty($projectName)  && !empty($assignedTo)) {
            // $log  = "====================\n";
            // $log .= "Task Time - " . date('Y-m-d H:i:s') . "\n";
            // $log .= "Task ID: $taskId\n";
            // $log .= "Title: $taskName\n";
            // $log .= "Creator ID: $creatorId\n";
            // $log .= "Project ID: $projectId\n";
            // $log .= "Project Name: $projectName\n";
            // $log .= "====================\n\n";

            $postData = [
                "projectName" => $projectName,
                "projectResponsiblePersonEmployeeId" => $creatorId,
                "tasks" => [
                    [
                        "goalName" => $taskName.'-'. $taskId,
                        "taskName" => $taskName.'-'. $taskId,
                        "taskResponsiblePersonEmployeeId" => $assignedTo
                    ]
                ]
        ];

        $ch = curl_init("https://webiators.v3.timechamp.io/swagger/api/tasks");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1laWQiOiIyY2ZkMzdjOS0zODE3LTRkYmQtODI4ZC1hYTY3MWFlY2E0YTMiLCJDb21wYW55IjoiMGY5ZTU1MjYtMDE0Mi00ZWQ2LWE4YmEtZjYwNWY5MGVhNGE0IiwibmJmIjoxNzEwODYzNTE5LCJleHAiOjIxNDI4NjM1MTksImlhdCI6MTcxMDg2MzUxOX0.d-YTpHdW4t_HWSuwUHX-Fm3s5LMZfX0IzwlQ8dKnxg8' // if required
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        // Log cURL response
        file_put_contents(WRITEPATH . 'logs/task_saved.txt', "cURL Response:\n$response\nError:\n$error\n", FILE_APPEND);
        } else {
            file_put_contents(WRITEPATH . 'logs/task_saved.txt', "All variables are not set\n", FILE_APPEND);
        }
    }
}
