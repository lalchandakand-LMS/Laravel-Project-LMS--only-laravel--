<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAndMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_message_creates_unread_message_and_notification_for_recipient_only(): void
    {
        $sender = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $recipient = User::factory()->create(['role' => 'instructor', 'is_active' => true]);

        $this->actingAs($sender)->post(route('messages.store'), [
            'recipient_id' => $recipient->id,
            'body' => 'Please help me with the next lesson.',
        ])->assertRedirect(route('messages.index'));

        $message = Message::firstOrFail();
        $this->assertSame($sender->id, $message->sender_id);
        $this->assertSame($recipient->id, $message->recipient_id);
        $this->assertNull($message->read_at);
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($recipient)
            ->get(route('communication.unread-counts'))
            ->assertOk()
            ->assertJson(['messages' => 1, 'notifications' => 1]);

        $this->actingAs($sender)
            ->get(route('communication.unread-counts'))
            ->assertOk()
            ->assertJson(['messages' => 0, 'notifications' => 0]);
    }

    public function test_reading_message_decrements_unread_message_count(): void
    {
        $sender = User::factory()->create(['role' => 'student']);
        $recipient = User::factory()->create(['role' => 'instructor']);
        $message = Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'body' => 'Unread message body',
        ]);

        $this->actingAs($recipient)
            ->get(route('communication.unread-counts'))
            ->assertJson(['messages' => 1]);

        $this->actingAs($recipient)
            ->post(route('messages.read', $message))
            ->assertRedirect();

        $this->assertNotNull($message->fresh()->read_at);
        $this->actingAs($recipient)
            ->get(route('communication.unread-counts'))
            ->assertJson(['messages' => 0]);
    }

    public function test_only_recipient_can_mark_message_as_read(): void
    {
        $sender = User::factory()->create(['role' => 'student']);
        $recipient = User::factory()->create(['role' => 'instructor']);
        $message = Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'body' => 'Private message',
        ]);

        $this->actingAs($sender)->post(route('messages.read', $message))->assertForbidden();
        $this->assertNull($message->fresh()->read_at);
    }

    public function test_reading_notification_decrements_only_that_users_unread_count(): void
    {
        $sender = User::factory()->create(['role' => 'student']);
        $recipient = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'student']);
        $message = Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'body' => 'Notification test',
        ]);
        $recipient->notify(new \App\Notifications\NewMessageNotification($message->load('sender')));
        $notification = $recipient->notifications()->firstOrFail();

        $this->actingAs($recipient)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('messages.index'));

        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($recipient)
            ->get(route('communication.unread-counts'))
            ->assertJson(['notifications' => 0]);

        $this->actingAs($other)
            ->get(route('communication.unread-counts'))
            ->assertJson(['notifications' => 0, 'messages' => 0]);
    }

    public function test_message_inbox_and_notification_inbox_render(): void
    {
        $sender = User::factory()->create(['role' => 'student']);
        $recipient = User::factory()->create(['role' => 'instructor']);
        Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'body' => 'Inbox rendering check',
        ]);

        $this->actingAs($recipient)->get(route('messages.index'))
            ->assertOk()->assertSee('Inbox rendering check')->assertSee('Compose message');
        $this->actingAs($recipient)->get(route('notifications.index'))
            ->assertOk()->assertSee('You have no notifications yet.');
    }
}
