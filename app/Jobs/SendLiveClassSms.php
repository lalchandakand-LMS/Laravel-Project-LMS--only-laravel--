<?php

namespace App\Jobs;

use App\Models\LiveClass;
use App\Models\User;
use App\Services\Sms\MiMSmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLiveClassSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 25;

    public function __construct(
        public readonly int $userId,
        public readonly int $liveClassId,
        public readonly string $event,
        public readonly string $notificationId
    ) {
        $this->onQueue('notifications');
    }

    public function handle(MiMSmsGateway $gateway): void
    {
        if (! config('live_classes.sms_notifications')) {
            return;
        }

        $settings = (array) config('live_classes.sms', []);
        foreach (['username', 'api_key', 'sender_name', 'endpoint'] as $key) {
            if (blank($settings[$key] ?? null)) {
                Log::warning('Live class SMS job skipped because MiMSMS configuration is incomplete.', [
                    'user_id' => $this->userId,
                    'live_class_id' => $this->liveClassId,
                    'missing_setting' => $key,
                ]);
                return;
            }
        }

        $user = User::query()->whereKey($this->userId)->where('is_active', true)->first();
        $liveClass = LiveClass::query()->with('course')->find($this->liveClassId);

        if (! $user || ! $liveClass || blank($user->phone)) {
            return;
        }

        $number = $gateway->normalizeBangladeshNumber($user->phone);
        if (! $number) {
            Log::notice('Live class SMS skipped: invalid Bangladesh mobile number.', [
                'user_id' => $user->id,
                'live_class_id' => $liveClass->id,
            ]);
            return;
        }

        $message = match ($this->event) {
            'cancelled' => 'Alom English Center: '.$liveClass->title.' has been cancelled. '.($liveClass->cancellation_reason ? 'Reason: '.$liveClass->cancellation_reason.'. ' : '').'Check your LMS for details.',
            'unpublished' => 'Alom English Center: '.$liveClass->title.' is no longer published and is unavailable in your LMS schedule.',
            'updated' => 'Alom English Center: Details for '.$liveClass->title.' have changed. Check your LMS schedule for the latest joining information.',
            'rescheduled' => 'Alom English Center: '.$liveClass->title.' is rescheduled to '.$liveClass->starts_at?->format('d M Y, h:i A').'. Check your LMS for details.',
            default => 'Alom English Center: '.$liveClass->title.' is scheduled for '.$liveClass->starts_at?->format('d M Y, h:i A').'. Check your LMS for details.',
        };

        $result = $gateway->sendTransactional($number, $message);

        Log::info('Live class SMS accepted by MiMSMS.', [
            'user_id' => $user->id,
            'live_class_id' => $liveClass->id,
            'event' => $this->event,
            'notification_id' => $this->notificationId,
            'provider_tracking_id' => $result['tracking_id'],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Live class SMS delivery failed after retries.', [
            'user_id' => $this->userId,
            'live_class_id' => $this->liveClassId,
            'event' => $this->event,
            'notification_id' => $this->notificationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
