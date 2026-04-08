<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_retrieve_their_notifications(): void
    {
        $user = User::factory()->create();

        Notification::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/notifications')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'type', 'message', 'read_at', 'is_read', 'created_at']],
                'meta' => ['current_page', 'total'],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->count(5)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson('/api/notifications')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_notifications_are_returned_newest_first(): void
    {
        $user = User::factory()->create();

        $old = Notification::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHour(),
        ]);

        $new = Notification::factory()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals($new->id, $ids->first());
    }

    public function test_notifications_are_paginated(): void
    {
        $user = User::factory()->create();

        Notification::factory()->count(20)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/notifications')
            ->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->unread()->create(['user_id' => $user->id]);

        $this->assertNull($notification->read_at);

        $this->actingAs($user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(200)
            ->assertJson(['message' => 'Notification marked as read.']);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }

    public function test_marking_nonexistent_notification_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/notifications/99999/read')
            ->assertStatus(404);
    }

    public function test_marking_an_already_read_notification_returns_422(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->read()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Notification has already been read.');
    }

    public function test_read_at_timestamp_is_preserved_when_notification_already_read(): void
    {
        $user = User::factory()->create();
        $readAt = now()->subHour()->startOfSecond();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => $readAt,
        ]);

        $this->actingAs($user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'read_at']);

        // Original read_at must not change
        $this->assertEquals(
            $readAt->toISOString(),
            $notification->fresh()->read_at->toISOString()
        );
    }
}
