<?php

namespace FeedbackGeoFM\Extensions;

use FeedbackGeoFM\Services\FeedbackService;

class TwigFeedbackServiceContainer
{
    /**
     * Expose the FeedbackService to Twig
     * @return mixed|null
     */
    public function getFeedback()
    {
        return pluginApp(FeedbackService::class);
    }
}
