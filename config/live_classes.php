<?php

return [
    // Email delivery is opt-in and requires a working Laravel mailer configuration.
    'email_notifications' => (bool) env('LIVE_CLASS_EMAIL_NOTIFICATIONS', false),
];
