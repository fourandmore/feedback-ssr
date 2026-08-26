<?php

namespace FeedbackGeoFM\Services {
    class FeedbackService
    {
        public function getFaqDataFromProperty($data, $propertyId, $variationId)
        {
            return [
                'status' => 'found',
                'jsonLd' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => [[
                        '@type' => 'Question',
                        'name' => 'Für welche Größe gilt das?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Zubehör und Fuß – geprüft bei 20 °C.'
                        ]
                    ]]
                ]
            ];
        }
    }
}

namespace Plenty\Plugin\Templates {
    class Twig
    {
    }
}

namespace {
    use FeedbackGeoFM\DataProviders\FaqPropertySchema;
    use FeedbackGeoFM\Services\FeedbackService;
    use Plenty\Plugin\Templates\Twig;

    $feedbackService = new FeedbackService();

    function pluginApp($className)
    {
        global $feedbackService;
        return $feedbackService;
    }

    require_once __DIR__ . '/../src/DataProviders/FaqPropertySchema.php';

    $provider = new FaqPropertySchema();
    $output = $provider->call(new Twig(), [[
        'item' => ['id' => 10420],
        'variation' => ['id' => 1034]
    ]]);

    $matches = [];
    preg_match('/<script\b[^>]*>(.*?)<\/script>/s', $output, $matches);
    $json = isset($matches[1]) ? $matches[1] : '';

    $assertions = [
        strpos($output, 'id="feedback-faq-property-jsonld-151"') !== false,
        strpos($json, 'Für') === false,
        strpos($json, '\\u00fcr') !== false,
        strpos($json, '\\u00f6') !== false,
        strpos($json, '\\u00df') !== false,
        strpos($json, '\\u2013') !== false,
        strpos($json, '\\u00b0') !== false
    ];

    $decoded = json_decode($json, true);
    $assertions[] = is_array($decoded);
    $assertions[] = isset($decoded['mainEntity'][0]['name'])
        && $decoded['mainEntity'][0]['name'] === 'Für welche Größe gilt das?';
    $assertions[] = isset($decoded['mainEntity'][0]['acceptedAnswer']['text'])
        && $decoded['mainEntity'][0]['acceptedAnswer']['text'] === 'Zubehör und Fuß – geprüft bei 20 °C.';

    foreach ($assertions as $assertion) {
        if (!$assertion) {
            fwrite(STDERR, 'FAQ JSON Unicode escaping regression test failed.' . PHP_EOL);
            exit(1);
        }
    }

    echo 'FAQ JSON Unicode escaping regression test passed.' . PHP_EOL;
}
