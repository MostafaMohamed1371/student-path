<?php

return [
    'tracking_interval_ms' => (int) env('SOS_TRACKING_INTERVAL_MS', 5000),

    'emergency_numbers' => [
        ['label' => 'اسعاف', 'number' => (string) env('SOS_EMERGENCY_AMBULANCE_NUMBER', '')],
        ['label' => 'شرطة', 'number' => (string) env('SOS_EMERGENCY_POLICE_NUMBER', '')],
        ['label' => 'الادارة', 'number' => (string) env('SOS_EMERGENCY_ADMIN_NUMBER', '')],
    ],
];
