<?php
$devices = [];
foreach (glob(__DIR__ . '/default_devices/*.php') as $file) {
    $devices = array_merge($devices, include($file));
}

// Add translations for device types
$deviceTypeTranslations = [
    'en' => [
        'Projector' => 'Projector',
        'Display' => 'Display',
        'Control Panel' => 'Control Panel',
        'Loudspeakers' => 'Loudspeakers',
        'Bluetooth audio interface' => 'Bluetooth audio interface',
        'Audio Mixer' => 'Audio Mixer',
        'Video Switcher' => 'Video Switcher',
        'Conference Camera' => 'Conference Camera',
        'Document Camera' => 'Document Camera',
        'Multi-format player' => 'Multi-format player',
        'Amplifier' => 'Amplifier',
    ],
    'fi' => [
        'Projector' => 'Projektori',
        'Display' => 'Näyttö',
        'Control Panel' => 'Ohjauspaneeli',
        'Loudspeakers' => 'Kaiuttimet',
        'Bluetooth audio interface' => 'Bluetooth-ääniliitäntä',
        'Audio Mixer' => 'Äänimikseri',
        'Video Switcher' => 'Videovaihtaja',
        'Conference Camera' => 'Konferenssikamera',
        'Document Camera' => 'Dokumenttikamera',
        'Multi-format player' => 'Moniformaattisoitin',
        'Amplifier' => 'Vahvistin',
    ],
    'sv' => [
        'Projector' => 'Projektor',
        'Display' => 'Bildskärm',
        'Control Panel' => 'Kontrollpanel',
        'Loudspeakers' => 'Högtalare',
        'Bluetooth audio interface' => 'Bluetooth-ljudgränssnitt',
        'Audio Mixer' => 'Ljudmixer',
        'Video Switcher' => 'Videoomkopplare',
        'Conference Camera' => 'Konferenskamera',
        'Document Camera' => 'Dokumentkamera',
        'Multi-format player' => 'Multiformatspelare',
        'Amplifier' => 'Förstärkare',
    ]
];

return [
    'devices' => $devices,
    'translations' => $deviceTypeTranslations
];
