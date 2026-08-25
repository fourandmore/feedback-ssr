<?php

namespace Plenty\Modules\Item\VariationProperty\Contracts {
    interface VariationPropertyValueRepositoryContract
    {
        public function findByVariationId($variationId);
    }

    interface VariationPropertyValueTextRepositoryContract
    {
        public function show($relationId, $language);
    }
}

namespace Plenty\Modules\Item\VariationProperty\Models {
    class VariationPropertyValue
    {
        public function toArray()
        {
            return [];
        }
    }

    class VariationPropertyValueText
    {
        public function toArray()
        {
            return [];
        }
    }
}

namespace {
    use FeedbackGeoFM\Services\FaqPropertySchemaBuilder;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueRepositoryContract;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueTextRepositoryContract;

    require_once __DIR__ . '/../src/Services/FaqPropertySchemaBuilder.php';

    class TestVariationPropertyValueRepository implements VariationPropertyValueRepositoryContract
    {
        public $requestedVariationIds = [];

        public function findByVariationId($variationId)
        {
            $variationId = (int)$variationId;
            $this->requestedVariationIds[] = $variationId;

            if ($variationId !== 7875) {
                return [];
            }

            return [[
                'id' => 42,
                'propertyId' => 151,
                'valueTexts' => [[
                    'lang' => 'de',
                    'value' => '<details class="faq-item"><summary>Ist die Markise wetterfest?</summary><div class="faq-answer"><p>Sie ist als Sonnenschutz ausgelegt.</p></div></details>'
                ]]
            ]];
        }
    }

    class TestVariationPropertyValueTextRepository implements VariationPropertyValueTextRepositoryContract
    {
        public function show($relationId, $language)
        {
            return null;
        }
    }

    $propertyRepository = new TestVariationPropertyValueRepository();
    $textRepository = new TestVariationPropertyValueTextRepository();
    $builder = new FaqPropertySchemaBuilder($propertyRepository, $textRepository);

    $result = $builder->build([
        'item' => [
            'id' => 600000
        ],
        'variation' => [
            'id' => 9001,
            'propertyVariationId' => 7875,
            'mainVariationId' => 7875
        ]
    ], 151, 'de', 9001);

    $assertions = [
        $result['status'] === 'found',
        $result['source'] === 'variation-repository',
        $result['resolvedVariationId'] === 7875,
        isset($result['jsonLd']['@type']) && $result['jsonLd']['@type'] === 'FAQPage',
        isset($result['jsonLd']['mainEntity'][0]['name'])
            && $result['jsonLd']['mainEntity'][0]['name'] === 'Ist die Markise wetterfest?',
        $propertyRepository->requestedVariationIds === [9001, 7875]
    ];

    foreach ($assertions as $assertion) {
        if (!$assertion) {
            fwrite(STDERR, 'FAQ inheritance regression test failed.' . PHP_EOL);
            exit(1);
        }
    }

    echo 'FAQ inheritance regression test passed.' . PHP_EOL;
}
