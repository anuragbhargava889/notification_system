<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Events\TaskAssigned;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_task(): void
    {
        $assigner = User::factory()->create();
        $assignee = User::factory()->create();

        $response = $this->actingAs($assigner)
            ->postJson('/api/tasks', [
                'title' => 'Build API',
                'description' => 'Create REST endpoints',
                'assigned_to' => $assignee->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'description', 'assigned_to', 'status', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.title', 'Build API')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Build API',
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_creating_a_task_dispatches_task_assigned_event(): void
    {
        Event::fake();

        $assigner = User::factory()->create();
        $assignee = User::factory()->create();

        $this->actingAs($assigner)
            ->postJson('/api/tasks', [
                'title' => 'Build API',
                'assigned_to' => $assignee->id,
            ]);

        Event::assertDispatched(TaskAssigned::class, function (TaskAssigned $event) use ($assignee, $assigner) {
            return $event->task->assigned_to === $assignee->id
                && $event->assigner->id === $assigner->id;
        });
    }

    public function test_creating_a_task_creates_a_notification_for_the_assignee(): void
    {
        $assigner = User::factory()->create(['name' => 'Alice']);
        $assignee = User::factory()->create();

        $this->actingAs($assigner)
            ->postJson('/api/tasks', [
                'title' => 'Build API',
                'assigned_to' => $assignee->id,
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
            'type' => NotificationType::TaskAssigned->value,
            'message' => 'User Alice assigned you task: Build API',
        ]);
    }

    public function test_task_creation_requires_title_and_assigned_to(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'assigned_to']);
    }

    public function test_assigned_to_must_be_an_existing_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks', [
                'title' => 'Test Task',
                'assigned_to' => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_status_must_be_a_valid_value(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks', [
                'title' => 'Test Task',
                'assigned_to' => $assignee->id,
                'status' => 'invalid_status',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_unauthenticated_user_cannot_create_a_task(): void
    {
        $this->postJson('/api/tasks', [
            'title' => 'Build API',
            'assigned_to' => 1,
        ])->assertStatus(401);
    }

    public function test_duplicate_task_title_for_same_assignee_is_rejected(): void
    {
        $assigner = User::factory()->create();
        $assignee = User::factory()->create();

        $payload = [
            'title' => 'Build API',
            'assigned_to' => $assignee->id,
        ];

        $this->actingAs($assigner)->postJson('/api/tasks', $payload)->assertStatus(201);

        $this->actingAs($assigner)
            ->postJson('/api/tasks', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'This task has already been assigned to the selected user.');
    }

    public function test_same_title_can_be_assigned_to_different_users(): void
    {
        $assigner = User::factory()->create();
        $assigneeA = User::factory()->create();
        $assigneeB = User::factory()->create();

        $this->actingAs($assigner)
            ->postJson('/api/tasks', ['title' => 'Build API', 'assigned_to' => $assigneeA->id])
            ->assertStatus(201);

        $this->actingAs($assigner)
            ->postJson('/api/tasks', ['title' => 'Build API', 'assigned_to' => $assigneeB->id])
            ->assertStatus(201);
    }
}
