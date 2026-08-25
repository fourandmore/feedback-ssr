<?php

namespace Plenty\Modules\Item\Item\Contracts {
    interface ItemRepositoryContract
    {
        public function show($itemId, $columns = [], $lang = 'de', $with = []);
    }
}

namespace Plenty\Modules\Item\Item\Models {
    class Item
    {
        public function toArray()
        {
            return [];
        }
    }
}

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
    use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
    use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueRepositoryContract;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueTextRepositoryContract;

    require_once __DIR__ . '/../src/Services/FaqPropertySchemaBuilder.php';

    class TestItemRepository implements ItemRepositoryContract
    {
        public $requests = [];

        public function show($itemId, $columns = [], $lang = 'de', $with = [])
        {
            $this->requests[] = [
                'itemId' => (int)$itemId,
                'columns' => $columns,
                'lang' => (string)$lang,
                'with' => $with
            ];

            if ((int)$itemId !== 600000) {
                return [];
            }

            return [
                'id' => 600000,
                'mainVariationId' => 7875,
                'properties' => [[
                    'id' => 55,
                    'propertyId' => 151,
                    'valueTexts' => [[
                        'lang' => 'de',
                        'value' => '<details class="faq-item"><summary>FAQ des Hauptartikels?</summary><div class="faq-answer"><p>Dieser Inhalt gilt für alle Varianten.</p></div></details>'
                    ]]
                ]]
            ];
        }
    }

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

            return [];
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
    $itemRepository = new TestItemRepository();
    $builder = new FaqPropertySchemaBuilder(
        $propertyRepository,
        $textRepository,
        $variationRepository,
        $itemRepository
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
        $result['source'] === 'main-item-repository',
        $result['resolvedVariationId'] === 7875,
        isset($result['jsonLd']['@type']) && $result['jsonLd']['@type'] === 'FAQPage',
        isset($result['jsonLd']['mainEntity'][0]['name'])
            && $result['jsonLd']['mainEntity'][0]['name'] === 'FAQ des Hauptartikels?',
        $variationRepository->requestedVariationIds === [9001],
        $propertyRepository->requestedVariationIds === [7875],
        count($itemRepository->requests) === 1,
        $itemRepository->requests[0]['itemId'] === 600000,
        $itemRepository->requests[0]['lang'] === 'de',
        $itemRepository->requests[0]['with'] === ['properties']
    ];

    foreach ($assertions as $assertion) {
        if (!$assertion) {
            fwrite(STDERR, 'FAQ main variation regression test failed.' . PHP_EOL);
            exit(1);
        }
    }

    echo 'FAQ main variation regression test passed.' . PHP_EOL;
}
