<?php

namespace Plenty\Modules\Item\Variation\Contracts {
    interface VariationRepositoryContract
    {
        public function findById($variationId);
    }
}

namespace Plenty\Modules\Item\Variation\Models {
    class Variation
    {
        public function toArray()
        {
            return [];
        }
    }
}

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
    use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueRepositoryContract;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueTextRepositoryContract;

    require_once __DIR__ . '/../src/Services/FaqPropertySchemaBuilder.php';

    class TestVariationRepository implements VariationRepositoryContract
    {
        public $requestedVariationIds = [];

        public function findById($variationId)
        {
            $variationId = (int)$variationId;
            $this->requestedVariationIds[] = $variationId;

            if ($variationId === 9001) {
                return [
                    'id' => 9001,
                    'isMain' => false,
                    'mainVariationId' => 7875
                ];
            }

            return [];
        }
    }

    class TestVariationPropertyValueRepository implements VariationPropertyValueRepositoryContract
    {
        public $requestedVariationIds = [];

        public function findByVariationId($variationId)
        {
            $variationId = (int)$variationId;
            $this->requestedVariationIds[] = $variationId;

            if ($variationId === 9001) {
                return [[
                    'id' => 41,
                    'propertyId' => 151,
                    'valueTexts' => [[
                        'lang' => 'de',
                        'value' => '<details class="faq-item"><summary>FAQ der Kindvariante?</summary><div class="faq-answer"><p>Dieser Inhalt darf nicht verwendet werden.</p></div></details>'
                    ]]
                ]];
            }

            if ($variationId !== 7875) {
                return [];
            }

            return [[
                'id' => 42,
                'propertyId' => 151,
                'valueTexts' => [[
                    'lang' => 'de',
                    'value' => '<details class="faq-item"><summary>FAQ der Hauptvariante?</summary><div class="faq-answer"><p>Dieser Inhalt gilt für alle Varianten.</p></div></details>'
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
    $variationRepository = new TestVariationRepository();
    $builder = new FaqPropertySchemaBuilder(
        $propertyRepository,
        $textRepository,
        $variationRepository
    );

    $result = $builder->build([
        'item' => [
            'id' => 600000
        ],
        'variation' => [
            'id' => 9001
        ],
        'properties' => [[
            'propertyId' => 151,
            'value' => '<details class="faq-item"><summary>FAQ im Kind-Dokument?</summary><div class="faq-answer"><p>Auch dieser Inhalt darf nicht verwendet werden.</p></div></details>'
        ]]
    ], 151, 'de', 9001);

    $assertions = [
        $result['status'] === 'found',
        $result['source'] === 'main-variation-repository',
        $result['resolvedVariationId'] === 7875,
        isset($result['jsonLd']['@type']) && $result['jsonLd']['@type'] === 'FAQPage',
        isset($result['jsonLd']['mainEntity'][0]['name'])
            && $result['jsonLd']['mainEntity'][0]['name'] === 'FAQ der Hauptvariante?',
        $variationRepository->requestedVariationIds === [9001],
        $propertyRepository->requestedVariationIds === [7875]
    ];

    foreach ($assertions as $assertion) {
        if (!$assertion) {
            fwrite(STDERR, 'FAQ main variation regression test failed.' . PHP_EOL);
            exit(1);
        }
    }

    echo 'FAQ main variation regression test passed.' . PHP_EOL;
}
