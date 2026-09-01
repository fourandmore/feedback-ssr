<?php

require_once __DIR__ . '/../src/Services/ProductSchemaBuilder.php';

use FeedbackGeoFM\Services\ProductSchemaBuilder;

function resolveTestShippingRate($profileId, $weightG, $configured, $defaultShippingCosts = null)
{
    $builder = new ProductSchemaBuilder();
    $variation = [
        'id' => 123,
        'number' => 'TEST-123',
        'weightG' => $weightG
    ];

    if ($defaultShippingCosts !== null) {
        $variation['defaultShippingCosts'] = $defaultShippingCosts;
    }

    $schema = $builder->build(
        [
            'texts' => ['name1' => 'Testprodukt'],
            'item' => ['id' => 999],
            'variation' => $variation,
            'filter' => [
                'isSalable' => true,
                'hasChildren' => false,
                'hasActiveChildren' => false
            ],
            'prices' => [
                'default' => [
                    'unitPrice' => ['value' => 100],
                    'currency' => 'EUR'
                ]
            ],
            'itemShippingProfiles' => [
                ['itemId' => 999, 'profileId' => $profileId]
            ]
        ],
        'https://example.test/test',
        [],
        [],
        'Seller',
        [
            'schemaShippingPolicy' => true,
            'schemaShippingCountries' => 'DE',
            'schemaShippingFallbackEnabled' => true,
            'schemaShippingProfilePrices' => $configured,
            'schemaReturnPolicy' => false
        ]
    );

    return isset($schema['offers']['shippingDetails']['shippingRate']['value'])
        ? $schema['offers']['shippingDetails']['shippingRate']['value']
        : null;
}

$cases = [
    ['profile 54 / 1 kg', resolveTestShippingRate(54, 1000, '54=29,90'), 29.9],
    ['profile 54 / 10 kg', resolveTestShippingRate(54, 10000, '54=29,90'), 49.9],
    ['profile 54 / 100 kg', resolveTestShippingRate(54, 100000, '54=29,90'), 99.0],
    ['profile 57 remains fixed', resolveTestShippingRate(57, 1000, '57=19,90', 6.9), 19.9]
];

foreach ($cases as $case) {
    $label = $case[0];
    $actual = $case[1];
    $expected = $case[2];

    if ($actual === null || abs((float)$actual - (float)$expected) > 0.001) {
        fwrite(STDERR, $label . ' failed.' . PHP_EOL);
        exit(1);
    }
}

echo 'Shipping profile 54 regression test passed.' . PHP_EOL;
