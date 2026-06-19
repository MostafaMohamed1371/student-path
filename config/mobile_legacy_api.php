<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy mobile API (GET /api/... alongside parent /api routes)
    |--------------------------------------------------------------------------
    */
    'support' => [
        'contact_methods' => [
            'whatsapp' => [
                'number' => env('MOBILE_SUPPORT_WHATSAPP', '+9647701234567'),
                'isAvailable' => (bool) env('MOBILE_SUPPORT_WHATSAPP_AVAILABLE', true),
                'availabilityNote' => env('MOBILE_SUPPORT_WHATSAPP_NOTE', 'متاح الآن للرد'),
            ],
            'phone' => [
                'number' => env('MOBILE_SUPPORT_PHONE', '6677'),
                'workingHours' => env('MOBILE_SUPPORT_PHONE_HOURS', 'يومياً من 9 صباحاً - 5 مساءً'),
            ],
            'liveChat' => [
                'isAvailable' => (bool) env('MOBILE_SUPPORT_LIVE_CHAT', true),
                'note' => env('MOBILE_SUPPORT_LIVE_CHAT_NOTE', 'تحدث مع موظف الدعم فوراً'),
            ],
        ],
        'faqs' => [
            [
                'id' => 'faq_1',
                'question' => 'كيف يمكنني تغيير كلمة المرور؟',
                'answer' => 'يمكنك تغيير كلمة المرور من خلال الإعدادات ثم الحماية...',
            ],
            [
                'id' => 'faq_2',
                'question' => 'مشكلة في دفع الفواتير؟',
                'answer' => 'يرجى التأكد من رصيد المحفظة أو التواصل مع الدعم الفني.',
            ],
            [
                'id' => 'faq_3',
                'question' => 'تحديث بيانات ولي الأمر؟',
                'answer' => 'يمكنك تحديث البيانات من قسم الملف الشخصي للطالب.',
            ],
        ],
        'categories' => [
            ['id' => '1', 'label' => 'مشكلة في الحساب'],
            ['id' => '2', 'label' => 'اقتراح لتحسين الخدمة'],
            ['id' => '3', 'label' => 'شكوى ضد سائق'],
        ],
        'complaint_max_attachments' => (int) env('MOBILE_SUPPORT_COMPLAINT_MAX_ATTACHMENTS', 5),
        'complaint_attachment_max_kb' => (int) env('MOBILE_SUPPORT_COMPLAINT_ATTACHMENT_MAX_KB', 5120),
    ],

    // Static fallback; per-user values come from user_notification_preferences (see config/notification_preferences.php).
    'notification_settings' => config('notification_preferences.defaults', []),
];
