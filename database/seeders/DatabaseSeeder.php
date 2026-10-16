<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Calendar;
use App\Models\Dependency;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceLeave;
use App\Models\Task;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();
        $project = Project::create([
            'name' => 'Website Redesign',
            'organization' => 'Acme Corp',
            'description' => 'Redesign the company website with modern UI/UX',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $calendar = Calendar::create([
            'project_id' => $project->id,
            'name' => 'Default Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'weekend_days' => [0, 6],
            'working_hours_per_day' => 8,
            'holidays' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newYear = Holiday::create([
            'calendar_id' => $calendar->id,
            'name' => 'New Year Day',
            'date' => '2026-01-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $independenceDay = Holiday::create([
            'calendar_id' => $calendar->id,
            'name' => 'Independence Day',
            'date' => '2026-07-04',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update calendar holidays with both holidays
        $calendar->update([
            'holidays' => [
                ['id' => (string) $newYear->id, 'name' => 'New Year Day', 'date' => '2026-01-01'],
                ['id' => (string) $independenceDay->id, 'name' => 'Independence Day', 'date' => '2026-07-04'],
            ],
            'updated_at' => now(),
        ]);

        // Create tasks
        $task1 = Task::create([
            'project_id' => $project->id,
            'parent_id' => null,
            'order' => 0,
            'name' => 'Requirements Gathering',
            'description' => 'Collect requirements from stakeholders',
            'start_input' => null,
            'duration' => 5,
            'priority' => 'High',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $task2 = Task::create([
            'project_id' => $project->id,
            'parent_id' => null,
            'order' => 1,
            'name' => 'Design Mockups',
            'description' => 'Create visual designs',
            'start_input' => null,
            'duration' => 8,
            'priority' => 'High',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $task3 = Task::create([
            'project_id' => $project->id,
            'parent_id' => null,
            'order' => 2,
            'name' => 'Development',
            'description' => 'Implement the website',
            'start_input' => null,
            'duration' => 15,
            'priority' => 'Critical',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $task4 = Task::create([
            'project_id' => $project->id,
            'parent_id' => null,
            'order' => 3,
            'name' => 'Testing',
            'description' => 'Test the website',
            'start_input' => null,
            'duration' => 7,
            'priority' => 'Medium',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $task5 = Task::create([
            'project_id' => $project->id,
            'parent_id' => null,
            'order' => 4,
            'name' => 'Deployment',
            'description' => 'Deploy to production',
            'start_input' => null,
            'duration' => 3,
            'priority' => 'Medium',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $task6 = Task::create([
            'project_id' => $project->id,
            'parent_id' => null,
            'order' => 5,
            'name' => 'Sign-off',
            'description' => 'Get client approval',
            'start_input' => null,
            'duration' => 0,
            'priority' => 'High',
            'percent_complete' => 0,
            'task_type' => 'Milestone',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create subtasks
        $subTask1 = Task::create([
            'project_id' => $project->id,
            'parent_id' => $task3->id,
            'order' => 0,
            'name' => 'Frontend Development',
            'description' => 'React components',
            'start_input' => null,
            'duration' => 8,
            'priority' => 'Critical',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subTask2 = Task::create([
            'project_id' => $project->id,
            'parent_id' => $task3->id,
            'order' => 1,
            'name' => 'Backend API',
            'description' => 'Node.js API',
            'start_input' => null,
            'duration' => 10,
            'priority' => 'Critical',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subTask3 = Task::create([
            'project_id' => $project->id,
            'parent_id' => $task3->id,
            'order' => 2,
            'name' => 'Database Setup',
            'description' => 'Database schema and setup',
            'start_input' => null,
            'duration' => 5,
            'priority' => 'Medium',
            'percent_complete' => 0,
            'task_type' => 'Task',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create dependencies
        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $task1->id,
            'successor_id' => $task2->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $task2->id,
            'successor_id' => $task3->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $task3->id,
            'successor_id' => $task4->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $task4->id,
            'successor_id' => $task5->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $task5->id,
            'successor_id' => $task6->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $subTask1->id,
            'successor_id' => $subTask2->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $subTask2->id,
            'successor_id' => $subTask3->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Dependency::create([
            'project_id' => $project->id,
            'predecessor_id' => $subTask3->id,
            'successor_id' => $task4->id,
            'type' => 'FS',
            'lag' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create resources
        $alice = Resource::create([
            'project_id' => $project->id,
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'phone' => '555-0101',
            'role' => 'UX Designer',
            'salary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bob = Resource::create([
            'project_id' => $project->id,
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'phone' => '555-0102',
            'role' => 'Frontend Developer',
            'salary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $carol = Resource::create([
            'project_id' => $project->id,
            'name' => 'Carol White',
            'email' => 'carol@example.com',
            'phone' => '555-0103',
            'role' => 'Backend Developer',
            'salary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dave = Resource::create([
            'project_id' => $project->id,
            'name' => 'Dave Brown',
            'email' => 'dave@example.com',
            'phone' => '555-0104',
            'role' => 'QA Engineer',
            'salary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eve = Resource::create([
            'project_id' => $project->id,
            'name' => 'Eve Davis',
            'email' => 'eve@example.com',
            'phone' => '555-0105',
            'role' => 'Project Manager',
            'salary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $frank = Resource::create([
            'project_id' => $project->id,
            'name' => 'Frank Miller',
            'email' => 'frank@example.com',
            'phone' => '555-0106',
            'role' => 'DevOps Engineer',
            'salary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create leaves
        ResourceLeave::create([
            'resource_id' => $bob->id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-17',
            'note' => 'Conference attendance',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create assignments
        Assignment::create([
            'project_id' => $project->id,
            'task_id' => $task1->id,
            'resource_id' => $alice->id,
            'role' => 'UX Designer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Assignment::create([
            'project_id' => $project->id,
            'task_id' => $task2->id,
            'resource_id' => $alice->id,
            'role' => 'UX Designer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Assignment::create([
            'project_id' => $project->id,
            'task_id' => $task3->id,
            'resource_id' => $bob->id,
            'role' => 'Frontend Developer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Assignment::create([
            'project_id' => $project->id,
            'task_id' => $task3->id,
            'resource_id' => $carol->id,
            'role' => 'Backend Developer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Assignment::create([
            'project_id' => $project->id,
            'task_id' => $task4->id,
            'resource_id' => $dave->id,
            'role' => 'QA Engineer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Assignment::create([
            'project_id' => $project->id,
            'task_id' => $task5->id,
            'resource_id' => $frank->id,
            'role' => 'DevOps Engineer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
