<?php

return [
    // Keep external delivery disabled until valid production credentials are installed.
    'email_notifications' => (bool) env('LIVE_CLASS_EMAIL_NOTIFICATIONS', false),
    'sms_notifications' => (bool) env('LIVE_CLASS_SMS_NOTIFICATIONS', false),

    'sms' => [
        'provider' => env('LIVE_CLASS_SMS_PROVIDER', 'mimsms'),
        'endpoint' => env('MIMSMS_API_ENDPOINT', 'https://api.mimsms.com/api/V2/SMS'),
        'username' => env('MIMSMS_USERNAME'),
        'api_key' => env('MIMSMS_API_KEY'),
        'sender_name' => env('MIMSMS_SENDER_NAME'),
        'transaction_type' => 'T',
        'connect_timeout' => (int) env('LIVE_CLASS_SMS_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('LIVE_CLASS_SMS_TIMEOUT', 15),
    ],
];
