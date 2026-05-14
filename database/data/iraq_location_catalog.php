<?php

declare(strict_types=1);
use Database\Seeders\IraqLocationsSeeder;

/**
 * Hierarchical catalog for {@see IraqLocationsSeeder}:
 * governorate-level districts → areas (major cities / quarters) → neighborhoods with WGS84 (approximate centroids).
 *
 * This is a curated operational dataset for the parent app APIs, not an exhaustive government gazetteer.
 * Extend this file or replace it with import logic as needed.
 *
 * @return list<array{name: string, sort_order: int, areas: list<array{name: string, sort_order: int, neighborhoods: list<array{name: string, latitude: float, longitude: float}>}>}>
 */
return (static function (): array {
    $n = static function (string $name, float $latitude, float $longitude): array {
        return ['name' => $name, 'latitude' => $latitude, 'longitude' => $longitude];
    };

    /** @var list<array{name: string, sort_order: int, areas: list<array{name: string, sort_order: int, neighborhoods: list<array{name: string, latitude: float, longitude: float}>}>}> $catalog */
    $catalog = [];

    // --- Baghdad (rich detail; includes Karrada on reference point used in tests) ---
    $catalog[] = [
        'name' => 'Baghdad',
        'sort_order' => 1,
        'areas' => [
            [
                'name' => 'Rusafa',
                'sort_order' => 0,
                'neighborhoods' => [
                    $n('Al-Karrada', 33.3152, 44.3661),
                    $n('Bab Al-Moatham', 33.3406, 44.4009),
                    $n('Al-Fadhilia', 33.3521, 44.4212),
                    $n('Al-Uruba', 33.3280, 44.3950),
                ],
            ],
            [
                'name' => 'Karkh',
                'sort_order' => 1,
                'neighborhoods' => [
                    $n('Al-Jadriya', 33.2889, 44.3665),
                    $n('Al-Mansour (Karkh side)', 33.3044, 44.3412),
                    $n('Al-Dora', 33.2840, 44.3780),
                    $n('Al-Bayaa', 33.2700, 44.3200),
                ],
            ],
            [
                'name' => 'Al-Mansour',
                'sort_order' => 2,
                'neighborhoods' => [
                    $n('Al-Mansour center', 33.3044, 44.3412),
                    $n('Al-Amiriyah', 33.2867, 44.2489),
                    $n('Al-Ghazaliyah', 33.3100, 44.2900),
                ],
            ],
            [
                'name' => 'Sadr City',
                'sort_order' => 3,
                'neighborhoods' => [
                    $n('Sector 83', 33.3844, 44.4578),
                    $n('Sector 91', 33.3920, 44.4680),
                    $n('Al-Habibiyah', 33.3750, 44.4400),
                ],
            ],
            [
                'name' => 'Kadhimiyah',
                'sort_order' => 4,
                'neighborhoods' => [
                    $n('Kadhimiyah shrine quarter', 33.3800, 44.3450),
                    $n('Shula', 33.3950, 44.3200),
                    $n('Al-Hurriya', 33.3700, 44.3300),
                ],
            ],
            [
                'name' => 'Adhamiyah',
                'sort_order' => 5,
                'neighborhoods' => [
                    $n('Adhamiyah center', 33.3520, 44.3650),
                    $n('Al-Waziriyah', 33.3480, 44.3850),
                ],
            ],
            [
                'name' => 'New Baghdad',
                'sort_order' => 6,
                'neighborhoods' => [
                    $n('New Baghdad center', 33.4050, 44.5150),
                    $n('Al-Furat', 33.4120, 44.5300),
                ],
            ],
            [
                'name' => 'Palestine Street',
                'sort_order' => 7,
                'neighborhoods' => [
                    $n('Palestine Street central', 33.3300, 44.4100),
                    $n('Al-Bunuk', 33.3350, 44.4250),
                ],
            ],
            [
                'name' => 'Abu Ghraib',
                'sort_order' => 8,
                'neighborhoods' => [
                    $n('Abu Ghraib center', 33.3050, 44.1850),
                    $n('Al-Taqaddum', 33.3200, 44.2000),
                ],
            ],
            [
                'name' => 'Taji',
                'sort_order' => 9,
                'neighborhoods' => [
                    $n('Taji center', 33.5280, 44.2650),
                    $n('Al-Rashidiyah', 33.5400, 44.2800),
                ],
            ],
            [
                'name' => 'Mahmudiyah',
                'sort_order' => 10,
                'neighborhoods' => [
                    $n('Mahmudiyah center', 33.0620, 44.3580),
                    $n('Al-Latifiyah', 33.0800, 44.3400),
                ],
            ],
            [
                'name' => 'Madain',
                'sort_order' => 11,
                'neighborhoods' => [
                    $n('Al-Madain center', 33.0950, 44.5800),
                    $n('Jisr Diyala', 33.1100, 44.6000),
                ],
            ],
        ],
    ];

    // --- Other governorates: capital + major districts, two neighborhoods each (centroid + offset) ---
    /** @var list<array{name: string, sort_order: int, areas: list<array{name: string, lat: float, lng: float}>}> */
    $others = [
        ['name' => 'Basra', 'sort_order' => 2, 'areas' => [
            ['name' => 'Basra City Center', 'lat' => 30.5039, 'lng' => 47.7804],
            ['name' => 'Ashar', 'lat' => 30.5085, 'lng' => 47.7835],
            ['name' => 'Abu Al-Khasib', 'lat' => 30.5320, 'lng' => 47.9510],
            ['name' => 'Zubayr', 'lat' => 30.3920, 'lng' => 47.7080],
            ['name' => 'Shatt al-Arab', 'lat' => 30.5450, 'lng' => 47.8250],
            ['name' => 'Al-Qurnah', 'lat' => 31.0150, 'lng' => 47.4300],
        ]],
        ['name' => 'Nineveh', 'sort_order' => 3, 'areas' => [
            ['name' => 'Mosul (Left Bank)', 'lat' => 36.3450, 'lng' => 43.1450],
            ['name' => 'Mosul (Right Bank)', 'lat' => 36.3600, 'lng' => 43.1600],
            ['name' => 'Tal Afar', 'lat' => 36.3790, 'lng' => 42.4490],
            ['name' => 'Hamdaniya', 'lat' => 36.2720, 'lng' => 43.3770],
            ['name' => 'Sinjar', 'lat' => 36.3220, 'lng' => 41.8660],
        ]],
        ['name' => 'Al Anbar', 'sort_order' => 4, 'areas' => [
            ['name' => 'Ramadi', 'lat' => 33.4206, 'lng' => 43.3075],
            ['name' => 'Fallujah', 'lat' => 33.3550, 'lng' => 43.7860],
            ['name' => 'Haditha', 'lat' => 34.1397, 'lng' => 42.2611],
            ['name' => 'Rutba', 'lat' => 33.0380, 'lng' => 40.2850],
            ['name' => 'Al-Qaim', 'lat' => 34.3980, 'lng' => 40.6180],
        ]],
        ['name' => 'Babil', 'sort_order' => 5, 'areas' => [
            ['name' => 'Hillah', 'lat' => 32.4839, 'lng' => 44.4319],
            ['name' => 'Musayyib', 'lat' => 32.7780, 'lng' => 44.2900],
            ['name' => 'Mahawil', 'lat' => 32.6330, 'lng' => 44.3170],
            ['name' => 'Al-Hashimiyah', 'lat' => 32.3930, 'lng' => 44.6500],
        ]],
        ['name' => 'Karbala', 'sort_order' => 6, 'areas' => [
            ['name' => 'Karbala City', 'lat' => 32.6160, 'lng' => 44.0248],
            ['name' => 'Ain Al-Tamur', 'lat' => 32.1350, 'lng' => 43.6280],
            ['name' => 'Al-Hindiyah', 'lat' => 32.5460, 'lng' => 44.2210],
        ]],
        ['name' => 'Najaf', 'sort_order' => 7, 'areas' => [
            ['name' => 'Najaf City', 'lat' => 31.9996, 'lng' => 44.3148],
            ['name' => 'Kufa', 'lat' => 32.0510, 'lng' => 44.4400],
            ['name' => 'Mishkhab', 'lat' => 31.8040, 'lng' => 44.4900],
            ['name' => 'Al-Manathera', 'lat' => 31.9580, 'lng' => 44.2280],
        ]],
        ['name' => 'Diyala', 'sort_order' => 8, 'areas' => [
            ['name' => 'Baqubah', 'lat' => 33.7442, 'lng' => 44.6434],
            ['name' => 'Khanaqin', 'lat' => 34.3370, 'lng' => 45.3830],
            ['name' => 'Muqdadiyah', 'lat' => 33.9790, 'lng' => 44.9390],
            ['name' => 'Balad Ruz', 'lat' => 33.6970, 'lng' => 45.0780],
        ]],
        ['name' => 'Wasit', 'sort_order' => 9, 'areas' => [
            ['name' => 'Kut', 'lat' => 32.5128, 'lng' => 45.8181],
            ['name' => 'Al-Hay', 'lat' => 32.1740, 'lng' => 46.0430],
            ['name' => "Al-Na'maniyah", 'lat' => 32.5050, 'lng' => 45.2500],
            ['name' => 'Aziziyah', 'lat' => 32.9090, 'lng' => 45.0650],
        ]],
        ['name' => 'Maysan', 'sort_order' => 10, 'areas' => [
            ['name' => 'Amarah', 'lat' => 31.8359, 'lng' => 47.1448],
            ['name' => 'Ali Al-Gharbi', 'lat' => 32.4620, 'lng' => 47.1310],
            ['name' => 'Al-Kahlaa', 'lat' => 31.4280, 'lng' => 47.3190],
            ['name' => 'Al-Maimouna', 'lat' => 31.7640, 'lng' => 47.3050],
        ]],
        ['name' => 'Dhi Qar', 'sort_order' => 11, 'areas' => [
            ['name' => 'Nasiriyah', 'lat' => 31.0579, 'lng' => 46.2573],
            ['name' => 'Shatra', 'lat' => 31.4090, 'lng' => 45.7060],
            ['name' => 'Suq Al-Shuyukh', 'lat' => 30.8860, 'lng' => 46.2610],
            ['name' => 'Rifai', 'lat' => 31.2870, 'lng' => 46.0900],
        ]],
        ['name' => 'Muthanna', 'sort_order' => 12, 'areas' => [
            ['name' => 'Samawah', 'lat' => 31.3167, 'lng' => 45.2944],
            ['name' => 'Al-Rumaitha', 'lat' => 30.5280, 'lng' => 45.1990],
            ['name' => 'Al-Salman', 'lat' => 30.0960, 'lng' => 44.1460],
        ]],
        ['name' => 'Al-Qadisiyyah', 'sort_order' => 13, 'areas' => [
            ['name' => 'Diwaniyah', 'lat' => 32.0003, 'lng' => 44.9259],
            ['name' => 'Hamzah', 'lat' => 31.9630, 'lng' => 44.9220],
            ['name' => 'Shamiyah', 'lat' => 31.5550, 'lng' => 45.0210],
            ['name' => 'Afak', 'lat' => 32.0640, 'lng' => 45.2510],
        ]],
        ['name' => 'Saladin', 'sort_order' => 14, 'areas' => [
            ['name' => 'Tikrit', 'lat' => 34.5961, 'lng' => 43.6781],
            ['name' => 'Samarra', 'lat' => 34.1980, 'lng' => 43.8740],
            ['name' => 'Balad', 'lat' => 34.0140, 'lng' => 44.1460],
            ['name' => 'Dujail', 'lat' => 33.8460, 'lng' => 44.2340],
        ]],
        ['name' => 'Kirkuk', 'sort_order' => 15, 'areas' => [
            ['name' => 'Kirkuk City', 'lat' => 35.4681, 'lng' => 44.3922],
            ['name' => 'Daquq', 'lat' => 35.1700, 'lng' => 44.3970],
            ['name' => 'Hawija', 'lat' => 35.3240, 'lng' => 43.7760],
            ['name' => 'Al-Rashad', 'lat' => 35.1700, 'lng' => 43.8610],
        ]],
        ['name' => 'Erbil', 'sort_order' => 16, 'areas' => [
            ['name' => 'Erbil City', 'lat' => 36.1911, 'lng' => 44.0094],
            ['name' => 'Ankawa', 'lat' => 36.2280, 'lng' => 43.9630],
            ['name' => 'Shaqlawa', 'lat' => 36.4040, 'lng' => 44.3090],
            ['name' => 'Koy Sanjaq', 'lat' => 36.0820, 'lng' => 44.6280],
        ]],
        ['name' => 'Sulaymaniyah', 'sort_order' => 17, 'areas' => [
            ['name' => 'Sulaymaniyah City', 'lat' => 35.5568, 'lng' => 45.9861],
            ['name' => 'Chamchamal', 'lat' => 35.5360, 'lng' => 44.7680],
            ['name' => 'Penjwin', 'lat' => 35.6160, 'lng' => 46.1460],
            ['name' => 'Ranya', 'lat' => 36.2550, 'lng' => 45.1320],
        ]],
        ['name' => 'Duhok', 'sort_order' => 18, 'areas' => [
            ['name' => 'Duhok City', 'lat' => 36.8671, 'lng' => 42.9881],
            ['name' => 'Zakho', 'lat' => 37.1487, 'lng' => 42.6851],
            ['name' => 'Amedi', 'lat' => 37.0920, 'lng' => 43.4880],
            ['name' => 'Semel', 'lat' => 36.8580, 'lng' => 42.7350],
        ]],
        ['name' => 'Halabja', 'sort_order' => 19, 'areas' => [
            ['name' => 'Halabja City', 'lat' => 35.1778, 'lng' => 45.9861],
            ['name' => 'Khurmal', 'lat' => 35.1390, 'lng' => 45.7520],
            ['name' => 'Byara', 'lat' => 35.0820, 'lng' => 45.4420],
        ]],
    ];

    foreach ($others as $gov) {
        $areas = [];
        foreach ($gov['areas'] as $i => $a) {
            $la = $a['lat'];
            $lo = $a['lng'];
            $areas[] = [
                'name' => $a['name'],
                'sort_order' => $i,
                'neighborhoods' => [
                    $n($a['name'].' — central', $la, $lo),
                    $n($a['name'].' — outskirts', $la + 0.025, $lo + 0.025),
                ],
            ];
        }
        $catalog[] = [
            'name' => $gov['name'],
            'sort_order' => $gov['sort_order'],
            'areas' => $areas,
        ];
    }

    return $catalog;
})();
