@php

$amo->getOAuthClient()->getOAuthButton(
    [
        'title' => 'Установить интеграцию',
        'compact' => true,
        'class_name' => 'className',
        'color' => 'default',
        'error_callback' => 'handleOauthError',
        'state' => $state,
    ]
);

@endphp
