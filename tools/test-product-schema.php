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
    'schemaShippingProfilePrices' => '6=6,90; 9=79,50; 12=129.00',
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
    || $freightProfileSchema['offers']['shippingDetails']['shippingRate']['value'] !== 79.5) {
    fwrite(STDERR, 'Exact shipping-profile fallback must take precedence over freight fallback.' . PHP_EOL);
    exit(1);
}

$parcelProfileItemData = $packageFallbackItemData;
$parcelProfileItemData['variation']['shippingProfileId'] = 6;
$parcelProfileSchema = $builder->build(
    $parcelProfileItemData,
    'https://www.example.test/paket_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($parcelProfileSchema)
    || $parcelProfileSchema['offers']['shippingDetails']['shippingRate']['value'] !== 6.9) {
    fwrite(STDERR, 'Exact parcel shipping-profile fallback failed.' . PHP_EOL);
    exit(1);
}

$rootProfileItemData = $packageFallbackItemData;
$rootProfileItemData['shippingProfiles'] = [
    ['id' => 501, 'itemId' => 51000, 'profileId' => 12]
];
$rootProfileSchema = $builder->build(
    $rootProfileItemData,
    'https://www.example.test/root-shipping-profile_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($rootProfileSchema)
    || $rootProfileSchema['offers']['shippingDetails']['shippingRate']['value'] !== 129.0) {
    fwrite(STDERR, 'Top-level PlentyONE shippingProfiles profileId fallback failed.' . PHP_EOL);
    exit(1);
}

$ceresItemShippingProfilesData = $packageFallbackItemData;
$ceresItemShippingProfilesData['variation']['itemShippingProfiles'] = [
    ['id' => 901, 'itemId' => 51000, 'profileId' => 57]
];
$ceresItemShippingProfilesData['variation']['defaultShippingCosts'] = 6.9;
$ceresProfileOptions = array_merge($fallbackOptions, [
    'schemaShippingProfilePrices' => '19=6,90; 57=19,90'
]);
$ceresItemShippingProfilesSchema = $builder->build(
    $ceresItemShippingProfilesData,
    'https://www.example.test/ceres-item-shipping-profiles_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $ceresProfileOptions
);
if (!is_array($ceresItemShippingProfilesSchema)
    || $ceresItemShippingProfilesSchema['offers']['shippingDetails']['shippingRate']['value'] !== 19.9) {
    fwrite(STDERR, 'Ceres variation.itemShippingProfiles profileId fallback failed.' . PHP_EOL);
    exit(1);
}

$repositoryItemShippingProfilesData = $packageFallbackItemData;
$repositoryItemShippingProfilesData['itemShippingProfiles'] = [
    ['id' => 902, 'itemId' => 51000, 'profileId' => 57]
];
$repositoryItemShippingProfilesData['variation']['defaultShippingCosts'] = 6.9;
$repositoryProfileOptions = array_merge($fallbackOptions, [
    'schemaShippingProfilePrices' => '19=6,90; 57=19,90'
]);
$repositoryItemShippingProfilesSchema = $builder->build(
    $repositoryItemShippingProfilesData,
    'https://www.example.test/repository-item-shipping-profiles_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $repositoryProfileOptions
);
if (!is_array($repositoryItemShippingProfilesSchema)
    || $repositoryItemShippingProfilesSchema['offers']['shippingDetails']['shippingRate']['value'] !== 19.9) {
    fwrite(STDERR, 'Server-side ItemShippingProfiles repository relation fallback failed.' . PHP_EOL);
    exit(1);
}

$unknownProfileItemData = $packageFallbackItemData;
$unknownProfileItemData['variation']['shippingProfileId'] = 77;
$unknownProfileSchema = $builder->build(
    $unknownProfileItemData,
    'https://www.example.test/unknown-profile_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($unknownProfileSchema)
    || $unknownProfileSchema['offers']['shippingDetails']['shippingRate']['value'] !== 8.5) {
    fwrite(STDERR, 'Unknown shipping profile must fall back to legacy parcel/freight logic.' . PHP_EOL);
    exit(1);
}

$profilePriceWinsItemData = $packageFallbackItemData;
$profilePriceWinsItemData['variation']['shippingProfileId'] = 12;
$profilePriceWinsItemData['variation']['defaultShippingCosts'] = 4.2;
$profilePriceWinsSchema = $builder->build(
    $profilePriceWinsItemData,
    'https://www.example.test/profile-price-wins_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($profilePriceWinsSchema)
    || $profilePriceWinsSchema['offers']['shippingDetails']['shippingRate']['value'] !== 129.0) {
    fwrite(STDERR, 'Explicit shipping-profile price must take precedence over PlentyONE default shipping costs.' . PHP_EOL);
    exit(1);
}

// Child variants inside a ProductGroup must keep PlentyONE's concrete
// variation-specific shipping amount before the shared item-level profile
// fallback. This prevents one profile price from flattening different shipping
// rates across all sizes of a variant family.
$productGroupShippingParent = $itemData;
$productGroupShippingParent['variation']['id'] = 12000;
$productGroupShippingParent['variation']['number'] = '51000';
$productGroupShippingParent['filter']['isSalable'] = false;
$productGroupShippingParent['filter']['hasChildren'] = true;
$productGroupShippingParent['filter']['hasActiveChildren'] = true;
$productGroupShippingParent['attributes'] = [];

$productGroupShippingChild = $itemData;
$productGroupShippingChild['variation']['id'] = 12001;
$productGroupShippingChild['variation']['number'] = '51000-LARGE';
$productGroupShippingChild['variation']['defaultShippingCosts'] = 49.9;
$productGroupShippingChild['itemShippingProfiles'] = [
    ['id' => 903, 'itemId' => 51000, 'profileId' => 12]
];

$productGroupShippingSchema = $builder->build(
    $productGroupShippingParent,
    'https://www.example.test/product-group-shipping_51000_12000',
    [],
    [],
    'Four & More GmbH',
    array_merge($fallbackOptions, [
        'schemaVariantDocuments' => [
            $productGroupShippingParent,
            $productGroupShippingChild
        ],
        'schemaVideoObject' => false
    ])
);
if (!is_array($productGroupShippingSchema)
    || count($productGroupShippingSchema['hasVariant']) !== 1
    || $productGroupShippingSchema['hasVariant'][0]['offers']['shippingDetails']['shippingRate']['value'] !== 49.9) {
    fwrite(STDERR, 'ProductGroup child variant must prefer PlentyONE variation-specific shipping costs over item-level profile fallback.' . PHP_EOL);
    exit(1);
}

$unmappedPlentyPriceItemData = $packageFallbackItemData;
$unmappedPlentyPriceItemData['variation']['shippingProfileId'] = 77;
$unmappedPlentyPriceItemData['variation']['defaultShippingCosts'] = 4.2;
$unmappedPlentyPriceSchema = $builder->build(
    $unmappedPlentyPriceItemData,
    'https://www.example.test/unmapped-plenty-price_51000_13046',
    [],
    [],
    'Four & More GmbH',
    $fallbackOptions
);
if (!is_array($unmappedPlentyPriceSchema)
    || $unmappedPlentyPriceSchema['offers']['shippingDetails']['shippingRate']['value'] !== 4.2) {
    fwrite(STDERR, 'PlentyONE default shipping costs must remain authoritative when no explicit profile price matches.' . PHP_EOL);
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
    || $productGroup['hasVariant'][0]['sku'] !== '51000-600'
    || $productGroup['hasVariant'][0]['offers']['price'] !== '129.90'
    || $productGroup['hasVariant'][0]['offers']['shippingDetails']['shippingRate']['value'] !== 6.9
    || $productGroup['hasVariant'][1]['@id'] !== 'https://www.example.test/infrarotheizungen/mephisto-infrarotheizung_51000_13047#product-variation-13047'
    || $productGroup['hasVariant'][1]['offers']['price'] !== '129.90') {
    fwrite(STDERR, 'Non-salable parent variation must be a ProductGroup with complete salable child Products and no parent Offer.' . PHP_EOL);
    exit(1);
}


$attributeVariantA = $itemData;
$attributeVariantA['feedbackVariantAttributes'] = [
    ['name' => 'Größe', 'value' => '200 x 150 cm'],
    ['name' => 'Tuchfarbe', 'value' => 'Grau']
];
$attributeVariantB = $secondVariantItemData;
$attributeVariantB['feedbackVariantAttributes'] = [
    ['name' => 'Größe', 'value' => '250 x 200 cm'],
    ['name' => 'Tuchfarbe', 'value' => 'Sand']
];
$attributeProductGroup = $builder->build(
    $parentItemData,
    'https://www.example.test/infrarotheizungen/mephisto-infrarotheizung_51000_7875',
    [],
    [],
    'Four & More GmbH',
    array_merge($schemaOptions, [
        'schemaVariesBy' => '',
        'schemaVideoObject' => false,
        'schemaVariantDocuments' => [
            $parentItemData,
            $attributeVariantA,
            $attributeVariantB
        ]
    ])
);

if (!is_array($attributeProductGroup)
    || !in_array('https://schema.org/size', $attributeProductGroup['variesBy'], true)
    || !in_array('https://schema.org/color', $attributeProductGroup['variesBy'], true)
    || $attributeProductGroup['hasVariant'][0]['size'] !== '200 x 150 cm'
    || $attributeProductGroup['hasVariant'][0]['color'] !== 'Grau'
    || strpos($attributeProductGroup['hasVariant'][0]['name'], '200 x 150 cm') === false
    || strpos($attributeProductGroup['hasVariant'][0]['name'], 'Grau') === false
    || empty($attributeProductGroup['hasVariant'][0]['isVariantOf']['name'])
    || empty($attributeProductGroup['hasVariant'][0]['isVariantOf']['description'])
    || empty($attributeProductGroup['hasVariant'][0]['offers']['price'])) {
    fwrite(STDERR, 'ProductGroup variants must expose real size/color attributes, parent metadata and offers.' . PHP_EOL);
    exit(1);
}

$json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed.' . PHP_EOL);
    exit(1);
}

echo $json . PHP_EOL;


// Shipping profile 54 is reserved for the markise shipping tiers represented
// by exact gross-weight markers in PlentyONE. Other profiles remain untouched.
foreach ([
    1000 => 29.9,
    10000 => 49.9,
    100000 => 99.0
] as $weightG => $expectedShippingPrice) {
    $profile54Data = $packageFallbackItemData;
    $profile54Data['variation']['weightG'] = $weightG;
    $profile54Data['itemShippingProfiles'] = [
        ['id' => 954, 'itemId' => 51000, 'profileId' => 54]
    ];
    $profile54Options = array_merge($fallbackOptions, [
        'schemaShippingProfilePrices' => '54=29,90; 57=19,90'
    ]);
    $profile54Schema = $builder->build(
        $profile54Data,
        'https://www.example.test/markise-profile54_' . $weightG,
        [],
        [],
        'Four & More GmbH',
        $profile54Options
    );
    if (!is_array($profile54Schema)
        || $profile54Schema['offers']['shippingDetails']['shippingRate']['value'] !== $expectedShippingPrice) {
        fwrite(STDERR, 'Shipping profile 54 weight tier failed for ' . $weightG . ' g.' . PHP_EOL);
        exit(1);
    }
}

// An unknown weight on profile 54 must fall back to the existing configured
// profile price rather than inventing a new tier.
$profile54UnknownWeightData = $packageFallbackItemData;
$profile54UnknownWeightData['variation']['weightG'] = 5000;
$profile54UnknownWeightData['itemShippingProfiles'] = [
    ['id' => 955, 'itemId' => 51000, 'profileId' => 54]
];
$profile54UnknownWeightSchema = $builder->build(
    $profile54UnknownWeightData,
    'https://www.example.test/markise-profile54-unknown-weight',
    [],
    [],
    'Four & More GmbH',
    array_merge($fallbackOptions, [
        'schemaShippingProfilePrices' => '54=29,90; 57=19,90'
    ])
);
if (!is_array($profile54UnknownWeightSchema)
    || $profile54UnknownWeightSchema['offers']['shippingDetails']['shippingRate']['value'] !== 29.9) {
    fwrite(STDERR, 'Shipping profile 54 unknown weight fallback failed.' . PHP_EOL);
    exit(1);
}

// A different profile with the same weight must keep its normal fixed mapping.
$profile57SameWeightData = $packageFallbackItemData;
$profile57SameWeightData['variation']['weightG'] = 100000;
$profile57SameWeightData['itemShippingProfiles'] = [
    ['id' => 957, 'itemId' => 51000, 'profileId' => 57]
];
$profile57SameWeightSchema = $builder->build(
    $profile57SameWeightData,
    'https://www.example.test/profile57-same-weight',
    [],
    [],
    'Four & More GmbH',
    array_merge($fallbackOptions, [
        'schemaShippingProfilePrices' => '54=29,90; 57=19,90'
    ])
);
if (!is_array($profile57SameWeightSchema)
    || $profile57SameWeightSchema['offers']['shippingDetails']['shippingRate']['value'] !== 19.9) {
    fwrite(STDERR, 'Non-54 shipping profile must not use markise weight tiers.' . PHP_EOL);
    exit(1);
}

