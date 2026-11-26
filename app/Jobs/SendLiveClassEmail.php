<?php

namespace App\Jobs;

use App\Models\LiveClass;
use App\Models\User;
use App\Notifications\LiveClassChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendLiveClassEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 30;

    public function __construct(
        public readonly int $userId,
        public readonly int $liveClassId,
        public readonly string $event,
        public readonly string $notificationId
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (! config('live_classes.email_notifications')) {
            return;
        }

        $smtp = (array) config('mail.mailers.smtp', []);
        if (config('mail.default') !== 'smtp'
            || blank($smtp['host'] ?? null)
            || blank($smtp['username'] ?? null)
            || blank($smtp['password'] ?? null)
            || blank(config('mail.from.address'))) {
            Log::warning('Live class email job skipped because SMTP configuration is incomplete.', [
                'user_id' => $this->userId,
                'live_class_id' => $this->liveClassId,
            ]);
            return;
        }

        $user = User::query()->whereKey($this->userId)->where('is_active', true)->first();
        $liveClass = LiveClass::query()->find($this->liveClassId);

        if (! $user || blank($user->email) || ! $liveClass) {
            return;
        }

        Notification::sendNow($user, new LiveClassChanged($liveClass, $this->event), ['mail']);

        Log::info('Live class email handed to configured mail transport.', [
            'user_id' => $user->id,
            'live_class_id' => $liveClass->id,
            'event' => $this->event,
            'notification_id' => $this->notificationId,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Live class email delivery failed after retries.', [
            'user_id' => $this->userId,
            'live_class_id' => $this->liveClassId,
            'event' => $this->event,
            'notification_id' => $this->notificationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
