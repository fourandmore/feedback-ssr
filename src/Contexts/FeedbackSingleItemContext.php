<?php

namespace FeedbackGeoFM\Contexts;

use Ceres\Contexts\SingleItemContext;
use IO\Helper\ContextInterface;

/**
 * Extends the documented Ceres SingleItemContext and forwards the already
 * loaded variation selector data into the current item document. Layout
 * containers on the single item page receive item.documents[0].data, so this
 * keeps FeedbackGeoFM on the same Ceres data source instead of discovering
 * sibling variations with separate search heuristics.
 */
class FeedbackSingleItemContext extends SingleItemContext implements ContextInterface
{
    /**
     * @param mixed $params
     * @return void
     */
    public function init($params)
    {
        parent::init($params);

        if (!isset($this->item['documents'][0]['data'])
            || !is_array($this->item['documents'][0]['data'])) {
            return;
        }

        $this->item['documents'][0]['data']['feedbackGeoFMContextVariations'] =
            is_array($this->variations) ? $this->variations : [];

        $this->item['documents'][0]['data']['feedbackGeoFMContextAttributes'] =
            is_array($this->attributes) ? $this->attributes : [];

        $this->item['documents'][0]['data']['feedbackGeoFMContextAfterKey'] =
            is_array($this->afterKey) ? $this->afterKey : [];
    }
}
