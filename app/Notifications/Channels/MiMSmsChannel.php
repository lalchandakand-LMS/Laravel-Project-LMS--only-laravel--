<?php

namespace App\Notifications\Channels;

use App\Jobs\SendLiveClassSms;
use App\Models\LiveClass;
use App\Notifications\LiveClassChanged;
use Illuminate\Support\Facades\Log;

class MiMSmsChannel
{
    public function send(object $notifiable, LiveClassChanged $notification): void
    {
        if (! config('live_classes.sms_notifications') || blank($notifiable->phone)) {
            return;
        }

        $settings = (array) config('live_classes.sms', []);
        foreach (['username', 'api_key', 'sender_name', 'endpoint'] as $key) {
            if (blank($settings[$key] ?? null)) {
                Log::warning('Live class SMS is enabled but MiMSMS credentials are incomplete; delivery was not queued.', [
                    'missing_setting' => $key,
                ]);
                return;
            }
        }

        $liveClass = $notification->liveClass();
        if (! $liveClass instanceof LiveClass) {
            return;
        }

        SendLiveClassSms::dispatch(
            (int) $notifiable->getKey(),
            (int) $liveClass->getKey(),
            $notification->event(),
            (string) $notification->id
        );
    }
}
