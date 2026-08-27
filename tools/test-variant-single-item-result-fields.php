<?php

$source = file_get_contents(__DIR__ . '/../src/DataProviders/ProductOfferSchema.php');

$checks = [
    'use Plenty\\Modules\\Webshop\\ItemSearch\\Helpers\\ResultFieldTemplate;',
    'array_chunk($variationIds, 20)',
    'ResultFieldTemplate::TEMPLATE_SINGLE_ITEM',
    '$itemService->getVariations('
];

foreach ($checks as $check) {
    if (strpos($source, $check) === false) {
        fwrite(STDERR, 'Missing documented variant loading construct: ' . $check . PHP_EOL);
        exit(1);
    }
}

echo "SingleItem result-field variant loading test passed.\n";
