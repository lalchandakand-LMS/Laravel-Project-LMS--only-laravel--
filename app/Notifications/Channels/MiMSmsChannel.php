<?php

namespace App\Notifications\Channels;

use App\Jobs\SendLiveClassSms;
use App\Models\LiveClass;
use App\Notifications\LiveClassChanged;

class MiMSmsChannel
{
    public function send(object $notifiable, LiveClassChanged $notification): void
    {
        if (! config('live_classes.sms_notifications') || blank($notifiable->phone)) {
            return;
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
