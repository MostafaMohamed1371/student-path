<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support live chat (Pusher)
    |--------------------------------------------------------------------------
    |
    | Mobile clients subscribe to private channel "chat.{conversationId}" via
    | Laravel Echo after POST /broadcasting/auth (Bearer Sanctum token).
    |
    */
    'private_channel_template' => 'chat.{conversationId}',

    'event_name' => 'message.sent',

    'max_message_length' => 5000,

    'default_currency' => env('CHAT_DEFAULT_CURRENCY', 'IQD'),

    'support_display_name' => env('CHAT_SUPPORT_DISPLAY_NAME', 'Support'),

    'attachment_disk' => env('CHAT_ATTACHMENT_DISK', 'public'),

    'attachment_path' => 'chat-attachments',

    'attachment_max_kb' => 20480,

    'attachment_mimes' => 'jpg,jpeg,png,webp,pdf,doc,docx,xlsx,txt,zip',

    'typing_event_name' => 'typing.updated',

    'message_updated_event_name' => 'message.updated',

    'offer_updated_event_name' => 'offer.updated',

];
