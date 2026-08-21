<?php

return [
    'enums' => [
        'panel' => 'App\\Enums\\System\\PanelEnum',
        'launcher' => 'App\\Enums\\System\\LauncherEnum',
    ],
    'ui' => [
        'density' => env('ACCELERATOR_UI_DENSITY', 'compact'),
        'sidebar' => [
            'default_width' => 336,
            'min_width' => 288,
            'max_width' => 480,
            'rail_width' => 56,
            'compact_rail_width' => 52,
        ],
    ],
];
