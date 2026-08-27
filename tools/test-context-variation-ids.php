<?php

require_once __DIR__ . '/../src/DataProviders/ProductOfferSchema.php';

$provider = new \FeedbackGeoFM\DataProviders\ProductOfferSchema();
$method = new ReflectionMethod($provider, 'extractSalableVariationIds');
$method->setAccessible(true);

$map = [
    ['variationId' => 7881, 'isSalable' => true, 'attributes' => []],
    ['variationId' => 7895, 'isSalable' => false, 'attributes' => []],
    ['nested' => [
        ['variationId' => 7903, 'isSalable' => true, 'attributes' => []],
        ['variationId' => 7881, 'isSalable' => true, 'attributes' => []]
    ]]
];

$ids = [];
$result = $method->invokeArgs($provider, [$map, 0, &$ids]);
sort($result);

if ($result !== [7881, 7903]) {
    fwrite(STDERR, 'Context variation ID extraction failed: ' . json_encode($result) . PHP_EOL);
    exit(1);
}

echo "Context variation ID extraction test passed.\n";
