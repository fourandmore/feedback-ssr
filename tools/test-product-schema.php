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
        ]
    ],
    'variation' => [
        'id' => 13046,
        'number' => '51000-600',
        'model' => 'IR-600'
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

$builder = new ProductSchemaBuilder();
$schema = $builder->build(
    $itemData,
    'https://www.example.test/infrarotheizung_51000_13046',
    $counts,
    $reviews,
    'Four & More GmbH'
);

$assertions = [
    $schema['@type'] === 'Product',
    $schema['name'] === 'Mephisto Infrarotheizung 600 W',
    $schema['sku'] === '51000-600',
    $schema['brand']['name'] === 'Mephisto',
    $schema['offers']['price'] === '129.90',
    $schema['offers']['priceCurrency'] === 'EUR',
    $schema['offers']['availability'] === 'https://schema.org/InStock',
    $schema['offers']['seller']['name'] === 'Four & More GmbH',
    $schema['aggregateRating']['reviewCount'] === 12,
    count($schema['review']) === 1,
    $schema['gtin13'] === '4012345678901'
];

foreach ($assertions as $index => $passed) {
    if (!$passed) {
        fwrite(STDERR, 'Assertion failed: ' . ($index + 1) . PHP_EOL);
        exit(1);
    }
}



$withoutReviews = $builder->build(
    $itemData,
    'https://www.example.test/infrarotheizung_51000_13046',
    ['averageValue' => 0, 'ratingsCountTotal' => 0],
    [],
    'Four & More GmbH'
);

if (!is_array($withoutReviews)
    || !isset($withoutReviews['offers'])
    || isset($withoutReviews['aggregateRating'])
    || isset($withoutReviews['review'])) {
    fwrite(STDERR, 'Product/Offer without reviews failed.' . PHP_EOL);
    exit(1);
}

$json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed.' . PHP_EOL);
    exit(1);
}

echo $json . PHP_EOL;
