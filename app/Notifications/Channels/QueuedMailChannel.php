<?php

namespace App\Notifications\Channels;

use App\Jobs\SendLiveClassEmail;
use App\Models\LiveClass;
use App\Notifications\LiveClassChanged;

class QueuedMailChannel
{
    public function send(object $notifiable, LiveClassChanged $notification): void
    {
        if (! config('live_classes.email_notifications') || blank($notifiable->email)) {
            return;
        }

        $liveClass = $notification->liveClass();
        if (! $liveClass instanceof LiveClass) {
            return;
        }

        SendLiveClassEmail::dispatch(
            (int) $notifiable->getKey(),
            (int) $liveClass->getKey(),
            $notification->event(),
            (string) $notification->id
        );
    }
}
