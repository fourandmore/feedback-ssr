<?php

require_once __DIR__ . '/../src/Services/ProductSchemaBuilder.php';

use FeedbackGeoFM\Services\ProductSchemaBuilder;

$itemData = [
    'texts' => [
        'name1' => 'Mephisto Infrarotheizung 600 W',
        'shortDescription' => '<p>Infrarotheizung mit WLAN-Thermostat.</p>'
    ],
    'item' => [
        'id' => 51000,
        'salableVariationCount' => 4,
        'manufacturer' => [
            'externalName' => 'Mephisto',
            'responsibleName' => 'Four & More GmbH'
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
        'isSalable' => true,
        'hasChildren' => false,
        'hasActiveChildren' => false
    ],
    'attributes' => [
        ['attributeName' => 'Größe']
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
    'schemaManufacturerName' => 'Mephisto',
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
    $schema['productID'] === '13046',
    $schema['isVariantOf']['@type'] === 'ProductGroup',
    $schema['isVariantOf']['productGroupID'] === '51000',
    $schema['isVariantOf']['variesBy'][0] === 'https://schema.org/size',
    $schema['brand']['name'] === 'Mephisto',
    $schema['manufacturer']['name'] === 'Mephisto',
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

// Manufacturer, EU responsible person, brand and seller are independent
// roles. The configured manufacturer is authoritative per plugin set.
$fourMoreItemData = $itemData;
$fourMoreItemData['item']['manufacturer'] = [
    'externalName' => 'Eclipse',
    'legalName' => '',
    'name' => 'Eclipse',
    'responsibleName' => 'Four & More GmbH'
];
$fourMoreSchema = $builder->build(
    $fourMoreItemData,
    'https://www.example.test/markisen/vollkassettenmarkise_600000_7875',
    [],
    [],
    'Four & More GmbH',
    array_merge($schemaOptions, [
        'schemaManufacturerName' => 'Four & More GmbH',
        'schemaVideoObject' => false
    ])
);

if (!is_array($fourMoreSchema)
    || $fourMoreSchema['brand']['name'] !== 'Eclipse'
    || $fourMoreSchema['manufacturer']['name'] !== 'Four & More GmbH'
    || $fourMoreSchema['offers']['seller']['name'] !== 'Four & More GmbH') {
    fwrite(STDERR, 'Four More manufacturer role separation failed.' . PHP_EOL);
    exit(1);
}

$billiardItemData = $itemData;
$billiardItemData['item']['manufacturer'] = [
    'externalName' => 'Billiard-Royal',
    'legalName' => '',
    'name' => 'Billiard-Royal',
    'responsibleName' => 'Four & More GmbH'
];
$billiardSchema = $builder->build(
    $billiardItemData,
    'https://www.example.test/milan-8-ft_10500_1042',
    [],
    [],
    'Four & More GmbH',
    array_merge($schemaOptions, [
        'schemaManufacturerName' => 'Billiard-Royal',
        'schemaVideoObject' => false
    ])
);

if (!is_array($billiardSchema)
    || $billiardSchema['brand']['name'] !== 'Billiard-Royal'
    || $billiardSchema['manufacturer']['name'] !== 'Billiard-Royal'
    || $billiardSchema['offers']['seller']['name'] !== 'Four & More GmbH') {
    fwrite(STDERR, 'Billiard Royal manufacturer role separation failed.' . PHP_EOL);
    exit(1);
}

$automaticManufacturerSchema = $builder->build(
    $billiardItemData,
    'https://www.example.test/milan-8-ft_10500_1042',
    [],
    [],
    'Four & More GmbH',
    array_merge($schemaOptions, [
        'schemaManufacturerName' => '',
        'schemaVideoObject' => false
    ])
);

if (!is_array($automaticManufacturerSchema)
    || $automaticManufacturerSchema['manufacturer']['name'] !== 'Billiard-Royal') {
    fwrite(STDERR, 'Automatic manufacturer fallback must ignore responsibleName.' . PHP_EOL);
    exit(1);
}

// Calculated PlentyONE costs remain authoritative. If they are absent, an
// explicitly enabled merchant fallback selects parcel or freight without
// inventing an amount inside the builder.
$packageFallbackItemData = $itemData;
unset($packageFallbackItemData['variation']['defaultShippingCosts']);
$packageFallbackItemData['variation']['weightG'] = 10000;
$fallbackOptions = array_merge($schemaOptions, [
    'schemaShippingFallbackEnabled' => true,
    'schemaShippingPackagePrice' => '8,50',
    'schemaShippingFreightPrice' => '59.00',
    'schemaShippingFreightWeightThresholdKg' => 31.5,
    'schemaShippingFreightProfileIds' => '9,10',
    'schemaVideoObject' => false
]);

$packageFallbackSchema = $builder->build(
    $packageFallbackItemData,
    'https://www.example.test/werkzeugkoffer_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($packageFallbackSchema)
    || $packageFallbackSchema['offers']['shippingDetails']['shippingRate']['value'] !== 8.5) {
    fwrite(STDERR, 'Configured parcel shipping fallback failed.' . PHP_EOL);
    exit(1);
}

$freightWeightItemData = $packageFallbackItemData;
$freightWeightItemData['variation']['weightG'] = 40000;
$freightWeightSchema = $builder->build(
    $freightWeightItemData,
    'https://www.example.test/billardtisch_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($freightWeightSchema)
    || $freightWeightSchema['offers']['shippingDetails']['shippingRate']['value'] !== 59.0) {
    fwrite(STDERR, 'Configured freight fallback by gross weight failed.' . PHP_EOL);
    exit(1);
}

$freightProfileItemData = $packageFallbackItemData;
$freightProfileItemData['variation']['shippingProfileIds'] = [9];
$freightProfileSchema = $builder->build(
    $freightProfileItemData,
    'https://www.example.test/sperrgut_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($freightProfileSchema)
    || $freightProfileSchema['offers']['shippingDetails']['shippingRate']['value'] !== 59.0) {
    fwrite(STDERR, 'Configured freight fallback by shipping profile failed.' . PHP_EOL);
    exit(1);
}

$parentItemData = $itemData;
$parentItemData['variation']['id'] = 7875;
$parentItemData['variation']['number'] = '51000';
$parentItemData['filter']['isSalable'] = false;
$parentItemData['filter']['hasChildren'] = true;
$parentItemData['filter']['hasActiveChildren'] = true;
$parentItemData['attributes'] = [];

$secondVariantItemData = $itemData;
$secondVariantItemData['variation']['id'] = 13047;
$secondVariantItemData['variation']['number'] = '51000-900';
$secondVariantItemData['texts']['name1'] = 'Mephisto Infrarotheizung 900 W';
$secondVariantItemData['urls']['canonical'] = 'https://www.example.test/infrarotheizungen/mephisto-infrarotheizung_51000_13047';

$productGroup = $builder->build(
    $parentItemData,
    'https://www.example.test/infrarotheizungen/mephisto-infrarotheizung_51000_7875',
    ['averageValue' => 0, 'ratingsCountTotal' => 0],
    [],
    'Four & More GmbH',
    array_merge($schemaOptions, [
        'schemaVariesBy' => 'size,color',
        'schemaVideoObject' => false,
        'schemaVariantDocuments' => [
            $parentItemData,
            $itemData,
            $secondVariantItemData
        ]
    ])
);

if (!is_array($productGroup)
    || $productGroup['@type'] !== 'ProductGroup'
    || $productGroup['productGroupID'] !== '51000'
    || isset($productGroup['offers'])
    || isset($productGroup['sku'])
    || count($productGroup['variesBy']) !== 2
    || count($productGroup['hasVariant']) !== 2
    || $productGroup['hasVariant'][0]['productID'] !== '13046'
    || $productGroup['hasVariant'][1]['@id'] !== 'https://www.example.test/infrarotheizungen/mephisto-infrarotheizung_51000_13047#product-variation-13047') {
    fwrite(STDERR, 'Non-salable parent variation must be a ProductGroup without Offer.' . PHP_EOL);
    exit(1);
}

$json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed.' . PHP_EOL);
    exit(1);
}

echo $json . PHP_EOL;
