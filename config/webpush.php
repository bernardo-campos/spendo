<?php

use NotificationChannels\WebPush\PushSubscription;

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => null,
    ],
    'model' => PushSubscription::class,
    'table_name' => 'push_subscriptions',
    'database_connection' => null,
    'client_options' => ['timeout' => 15, 'connect_timeout' => 5],
    'automatic_padding' => true,
    'allowed_hosts' => [
        'web.push.apple.com',
        '*.push.apple.com',
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        '*.notify.windows.com',
    ],
];
