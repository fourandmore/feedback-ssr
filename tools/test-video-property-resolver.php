<?php

require_once __DIR__ . '/../src/Services/VideoPropertyResolver.php';

use FeedbackGeoFM\Services\VideoPropertyResolver;

$resolver = new VideoPropertyResolver();

$itemData = [
    'item' => ['id' => 73005],
    'variation' => ['id' => 13395],
    'variationProperties' => [
        [
            'id' => 8,
            'properties' => [
                [
                    'id' => 110,
                    'values' => ['value' => 'HY2oXdCfsus']
                ],
                [
                    'id' => 158,
                    'values' => ['value' => '2026-08-26']
                ]
            ]
        ]
    ]
];

$options = $resolver->resolve($itemData, 110, 158);

if (!is_array($options)
    || $options['schemaVideoObject'] !== true
    || $options['schemaVideoEmbedUrl'] !== 'https://www.youtube-nocookie.com/embed/HY2oXdCfsus'
    || $options['schemaVideoThumbnailUrl'] !== 'https://i.ytimg.com/vi/HY2oXdCfsus/hqdefault.jpg'
    || $options['schemaVideoUploadDate'] !== '2026-08-26') {
    fwrite(STDERR, "VariationProperties video resolution failed.\n");
    exit(1);
}

$urlData = [
    'variationProperties' => [
        ['properties' => [
            ['propertyId' => 110, 'value' => 'https://www.youtube.com/watch?v=AbCdEf12345'],
            ['propertyId' => 158, 'value' => '26.08.2026']
        ]]
    ]
];

$urlOptions = $resolver->resolve($urlData, 110, 158);
if (!is_array($urlOptions)
    || $urlOptions['schemaVideoEmbedUrl'] !== 'https://www.youtube-nocookie.com/embed/AbCdEf12345'
    || $urlOptions['schemaVideoUploadDate'] !== '2026-08-26') {
    fwrite(STDERR, "YouTube URL/German date normalization failed.\n");
    exit(1);
}

$invalid = $resolver->resolve([
    'variationProperties' => [[
        'properties' => [
            ['id' => 110, 'values' => ['value' => 'not a youtube id!']],
            ['id' => 158, 'values' => ['value' => '2026-08-26']]
        ]
    ]]
], 110, 158);

if ($invalid !== null) {
    fwrite(STDERR, "Invalid YouTube property must not generate VideoObject options.\n");
    exit(1);
}

echo "VideoPropertyResolver tests passed.\n";
