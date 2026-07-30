<?php

require_once __DIR__ . '/../src/Services/ProductSchemaBuilder.php';

use Feedback\Services\ProductSchemaBuilder;

$itemData = [
    'texts' => [
        'name1' => 'Mephisto Infrarotheizung 600 W',
        'shortDescription' => '<p>Infrarotheizung mit WLAN-Thermostat.</p>'
    ],
    'item' => [
        'id' => 51000,
        'manufacturer' => [
            'externalName' => 'Mephisto'
        ],
        'condition' => [
            'names' => [
                'name' => 'Neu'
            ]
        ]
    ],
    'variation' => [
        'id' => 13046,
        'number' => '51000-600',
        'model' => 'IR-600',
        'defaultShippingCosts' => 6.90
    ],
    'filter' => [
        'isSalable' => true
    ],
    'prices' => [
        'default' => [
            'unitPrice' => ['value' => 129.9],
            'currency' => 'EUR'
        ]
    ],
    'images' => [
        'variation' => [
            ['url' => 'https://example.test/image.jpg']
        ]
    ],
    'barcodes' => [
        ['code' => '4012345678901']
    ]
];

$counts = [
    'averageValue' => 4.8,
    'ratingsCountTotal' => 12
];

$reviews = [[
    '@type' => 'Review',
    'author' => ['@type' => 'Person', 'name' => 'Max M.'],
    'reviewBody' => 'Sehr gutes Produkt.',
    'reviewRating' => [
        '@type' => 'Rating',
        'ratingValue' => 5,
        'bestRating' => 5,
        'worstRating' => 1
    ]
]];

$schemaOptions = [
    'schemaShippingPolicy' => true,
    'schemaShippingCountries' => 'DE',
    'schemaHandlingTimeMin' => 0,
    'schemaHandlingTimeMax' => 1,
    'schemaTransitTimeMin' => 1,
    'schemaTransitTimeMax' => 3,
    'schemaReturnPolicy' => true,
    'schemaReturnCountries' => 'DE',
    'schemaReturnDays' => 14,
    'schemaReturnPolicyUrl' => 'https://www.example.test/widerrufsrecht/',
    'schemaVideoObject' => true,
    'schemaVideoName' => 'Mephisto Infrarotheizung im Überblick',
    'schemaVideoEmbedUrl' => 'https://www.youtube.com/embed/AbCdEf12345',
    'schemaVideoThumbnailUrl' => '',
    'schemaVideoUploadDate' => '2026-07-01',
    'schemaVideoDescription' => 'Funktionen und Montage der Infrarotheizung.',
    'schemaVideoDuration' => 'PT1M30S'
];

$builder = new ProductSchemaBuilder();
$schema = $builder->build(
    $itemData,
    'https://www.example.test/infrarotheizungen/mephisto-infrarotheizung_51000_13046',
    $counts,
    $reviews,
    'Four & More GmbH',
    $schemaOptions
);

$assertions = [
    $schema['@type'] === 'Product',
    $schema['name'] === 'Mephisto Infrarotheizung 600 W',
    $schema['sku'] === '51000-600',
    $schema['brand']['name'] === 'Mephisto',
    $schema['category'] === 'Infrarotheizungen',
    $schema['offers']['price'] === '129.90',
    $schema['offers']['priceCurrency'] === 'EUR',
    $schema['offers']['availability'] === 'https://schema.org/InStock',
    $schema['offers']['itemCondition'] === 'https://schema.org/NewCondition',
    $schema['offers']['seller']['name'] === 'Four & More GmbH',
    $schema['offers']['seller']['hasMerchantReturnPolicy']['merchantReturnDays'] === 14,
    $schema['offers']['seller']['hasMerchantReturnPolicy']['returnFees'] === 'https://schema.org/ReturnFeesCustomerResponsibility',
    $schema['offers']['shippingDetails']['shippingRate']['value'] === 6.9,
    $schema['offers']['shippingDetails']['shippingRate']['currency'] === 'EUR',
    $schema['offers']['shippingDetails']['shippingDestination']['addressCountry'] === 'DE',
    $schema['aggregateRating']['reviewCount'] === 12,
    count($schema['review']) === 1,
    $schema['gtin13'] === '4012345678901',
    $schema['subjectOf']['@type'] === 'VideoObject',
    $schema['subjectOf']['thumbnailUrl'] === 'https://i.ytimg.com/vi/AbCdEf12345/hqdefault.jpg',
    $schema['subjectOf']['duration'] === 'PT1M30S'
];

foreach ($assertions as $index => $passed) {
    if (!$passed) {
        fwrite(STDERR, 'Assertion failed: ' . ($index + 1) . PHP_EOL);
        exit(1);
    }
}

$withoutReviewsOrVideo = $builder->build(
    $itemData,
    'https://www.example.test/infrarotheizungen/mephisto-infrarotheizung_51000_13046',
    ['averageValue' => 0, 'ratingsCountTotal' => 0],
    [],
    'Four & More GmbH',
    array_merge($schemaOptions, ['schemaVideoObject' => false])
);

if (!is_array($withoutReviewsOrVideo)
    || !isset($withoutReviewsOrVideo['offers'])
    || isset($withoutReviewsOrVideo['aggregateRating'])
    || isset($withoutReviewsOrVideo['review'])
    || isset($withoutReviewsOrVideo['subjectOf'])) {
    fwrite(STDERR, 'Product/Offer without reviews or video failed.' . PHP_EOL);
    exit(1);
}

$json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed.' . PHP_EOL);
    exit(1);
}

echo $json . PHP_EOL;
