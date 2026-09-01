<?php

namespace IO\Helper {
    interface ContextInterface {}
}

namespace Ceres\Contexts {
    class SingleItemContext implements \IO\Helper\ContextInterface
    {
        public $item = [];
        public $attributes = [];
        public $variations = [];
        public $afterKey = [];

        public function init($params)
        {
            $this->item = $params['item'];
            $this->attributes = $params['variationAttributeMap']['attributes'];
            $this->variations = $params['variationAttributeMap']['variations'];
            $this->afterKey = $params['variationAttributeMap']['afterKey'];
        }
    }
}

namespace {
    require_once __DIR__ . '/../src/Contexts/FeedbackSingleItemContext.php';

    $ctx = new \FeedbackGeoFM\Contexts\FeedbackSingleItemContext();
    $ctx->init([
        'item' => [
            'documents' => [[
                'data' => [
                    'item' => ['id' => 600000],
                    'variation' => ['id' => 7875]
                ]
            ]]
        ],
        'variationAttributeMap' => [
            'attributes' => [['id' => 45, 'name' => 'Größe']],
            'variations' => [
                ['variationId' => 7881, 'isSalable' => true, 'attributes' => [
                    ['attributeId' => 45, 'attributeValueId' => 379]
                ]]
            ],
            'afterKey' => []
        ]
    ]);

    $data = $ctx->item['documents'][0]['data'];
    if (($data['feedbackGeoFMContextVariations'][0]['variationId'] ?? 0) !== 7881
        || ($data['feedbackGeoFMContextAttributes'][0]['name'] ?? '') !== 'Größe') {
        fwrite(STDERR, "SingleItemContext forwarding failed.\n");
        exit(1);
    }

    echo "SingleItemContext forwarding test passed.\n";
}
