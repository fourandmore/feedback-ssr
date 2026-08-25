<?php

namespace FeedbackGeoFM\DataProviders;

use FeedbackGeoFM\Services\FeedbackService;

/**
 * Server-side FAQPage JSON-LD provider for plentyShop single item views.
 *
 * The ShopBuilder sanitises dynamic content inside normal <script> tags.
 * Rendering JSON-LD through a layout-container data provider avoids that
 * processing step and sends a populated application/ld+json script in the
 * original HTML response.
 */
class FaqPropertySchema
{
    const FAQ_PROPERTY_ID = 151;

    /**
     * The Ceres single-item container passes item.documents[0].data as the
     * final argument. Dependencies before it are resolved by the plugin DI.
     *
     * @param FeedbackService $feedbackService
     * @param mixed $itemData
     * @return string
     */
    public function call(FeedbackService $feedbackService, $itemData)
    {
        $data = is_array($itemData) ? $itemData : [];
        $variationId = $this->resolveVariationId($data);

        $faqData = $feedbackService->getFaqDataFromProperty(
            $data,
            self::FAQ_PROPERTY_ID,
            $variationId
        );

        if (!is_array($faqData)
            || !isset($faqData['status'])
            || $faqData['status'] !== 'found'
            || !isset($faqData['jsonLd'])
            || !is_array($faqData['jsonLd'])
            || empty($faqData['jsonLd']['mainEntity'])) {
            return '';
        }

        $json = json_encode(
            $faqData['jsonLd'],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if (!is_string($json) || $json === '') {
            return '';
        }

        return '<script id="feedback-faq-property-jsonld-'
            . self::FAQ_PROPERTY_ID
            . '" type="application/ld+json">'
            . $json
            . '</script>';
    }

    /**
     * @param array $data
     * @return int
     */
    private function resolveVariationId(array $data)
    {
        if (isset($data['variation'])
            && is_array($data['variation'])
            && isset($data['variation']['id'])
            && is_numeric($data['variation']['id'])) {
            return (int)$data['variation']['id'];
        }

        if (isset($data['variationId']) && is_numeric($data['variationId'])) {
            return (int)$data['variationId'];
        }

        return -1;
    }
}
