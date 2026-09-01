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

namespace Plenty\Modules\Webshop\ItemSearch\SearchPresets {
    class SingleItem
    {
        public static function getSearchFactory($options)
        {
            return $options;
        }
    }
}

namespace Plenty\Modules\Webshop\ItemSearch\Services {
    class ItemSearchService
    {
        public $requestedVariationIds = [];

        public function getResults($searchFactories)
        {
            $variationId = isset($searchFactories[0]['variationId'])
                ? (int)$searchFactories[0]['variationId']
                : 0;
            $this->requestedVariationIds[] = $variationId;

            if ($variationId !== 7875) {
                return [];
            }

            return [[
                'documents' => [[
                    'data' => [
                        'item' => [
                            'id' => 600000,
                            'mainVariationId' => 7875
                        ],
                        'variation' => [
                            'id' => 7875,
                            'isMain' => true
                        ],
                        'properties' => [[
                            'id' => 55,
                            'propertyId' => 151,
                            'valueTexts' => [[
                                'lang' => 'de',
                                'value' => '<details class="faq-item"><summary>F&amp;Atilde;&amp;frac14;r welche Gr&amp;Atilde;&amp;para;&amp;Atilde;&amp;#159;e gilt das?</summary><div class="faq-answer"><p>Der Zubeh&amp;Atilde;&amp;para;rumfang ist zuverl&amp;Atilde;&amp;curren;ssig &amp;Atilde;&amp;frac14;berpr&amp;Atilde;&amp;frac14;ft.</p></div></details><details class="faq-item"><summary>Bleiben Größe und Zubehör korrekt?</summary><div class="faq-answer"><p>Ja, bereits korrektes UTF-8 bleibt unverändert.</p></div></details>'
                            ]]
                        ]]
                    ]
                ]]
            ]];
        }
    }
}

namespace {
    use FeedbackGeoFM\Services\FaqPropertySchemaBuilder;
    use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
    use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueRepositoryContract;
    use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueTextRepositoryContract;
    use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;

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
    $itemSearchService = new ItemSearchService();
    $builder = new FaqPropertySchemaBuilder(
        $propertyRepository,
        $textRepository,
        $variationRepository,
        $itemRepository,
        $itemSearchService
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
        $result['source'] === 'main-variation-item-document',
        $result['resolvedVariationId'] === 7875,
        isset($result['jsonLd']['@type']) && $result['jsonLd']['@type'] === 'FAQPage',
        isset($result['jsonLd']['mainEntity'][0]['name'])
            && $result['jsonLd']['mainEntity'][0]['name'] === 'Für welche Größe gilt das?',
        isset($result['jsonLd']['mainEntity'][0]['acceptedAnswer']['text'])
            && $result['jsonLd']['mainEntity'][0]['acceptedAnswer']['text'] === 'Der Zubehörumfang ist zuverlässig überprüft.',
        isset($result['jsonLd']['mainEntity'][1]['name'])
            && $result['jsonLd']['mainEntity'][1]['name'] === 'Bleiben Größe und Zubehör korrekt?',
        isset($result['jsonLd']['mainEntity'][1]['acceptedAnswer']['text'])
            && $result['jsonLd']['mainEntity'][1]['acceptedAnswer']['text'] === 'Ja, bereits korrektes UTF-8 bleibt unverändert.',
        $variationRepository->requestedVariationIds === [9001],
        $itemSearchService->requestedVariationIds === [7875],
        $propertyRepository->requestedVariationIds === [],
        count($itemRepository->requests) === 0
    ];

    foreach ($assertions as $assertion) {
        if (!$assertion) {
            fwrite(STDERR, 'FAQ main variation regression test failed.' . PHP_EOL);
            exit(1);
        }
    }

    echo 'FAQ main variation regression test passed.' . PHP_EOL;
}
