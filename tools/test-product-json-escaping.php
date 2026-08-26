<?php

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => 'Großer, gefüllter Werkzeugwagen',
    'description' => 'Zubehör und Füße – geprüft bei 20 °C.',
    'review' => [[
        '@type' => 'Review',
        'name' => 'Überzeugende Qualität',
        'reviewBody' => 'Größe und Ausführung sind zuverlässig.'
    ]]
];

$json = json_encode(
    $jsonLd,
    JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);

$assertions = [
    is_string($json),
    strpos($json, 'Großer') === false,
    strpos($json, '\\u00f6') !== false,
    strpos($json, '\\u00df') !== false,
    strpos($json, '\\u00fc') !== false,
    strpos($json, '\\u2013') !== false,
    strpos($json, '\\u00b0') !== false,
    strpos($json, '&Atilde;') === false,
    strpos($json, '&frac14;') === false
];

$decoded = json_decode($json, true);
$assertions[] = is_array($decoded);
$assertions[] = isset($decoded['name'])
    && $decoded['name'] === 'Großer, gefüllter Werkzeugwagen';
$assertions[] = isset($decoded['review'][0]['reviewBody'])
    && $decoded['review'][0]['reviewBody'] === 'Größe und Ausführung sind zuverlässig.';

foreach ($assertions as $assertion) {
    if (!$assertion) {
        fwrite(STDERR, 'Product JSON Unicode escaping regression test failed.' . PHP_EOL);
        exit(1);
    }
}

echo 'Product JSON Unicode escaping regression test passed.' . PHP_EOL;
