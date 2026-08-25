<?php

namespace FeedbackGeoFM\DataProviders;

use FeedbackGeoFM\Services\FeedbackService;
use Plenty\Plugin\Templates\Twig;

/**
 * Server-side FAQPage JSON-LD provider for plentyShop single item views.
 *
 * Linked to Ceres::SingleItem.BeforePrice. plentyShop passes the current
 * item.documents[0].data object to the provider as the first container
 * argument. The provider returns a real application/ld+json script outside
 * ShopBuilder processing, so the JSON-LD is present in the initial HTML.
 */
class FaqPropertySchema
{
    const FAQ_PROPERTY_ID = 151;

    /**
     * @param Twig $twig
     * @param mixed $args Layout-container arguments. For
     *                    Ceres::SingleItem.BeforePrice, $args[0] contains
     *                    item.documents[0].data.
     * @return string
     */
    public function call(Twig $twig, $args)
    {
        /** @var FeedbackService $feedbackService */
        $feedbackService = pluginApp(FeedbackService::class);

        $data = $this->resolveItemData($args);
        if (empty($data)) {
            return '';
        }

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
     * Normalise the argument shape used by plentyShop layout containers.
     *
     * @param mixed $args
     * @return array
     */
    private function resolveItemData($args)
    {
        if (!is_array($args)) {
            return [];
        }

        // Standard layout-container shape: container(..., object) => $args[0].
        if (isset($args[0]) && is_array($args[0])) {
            return $args[0];
        }

        // Defensive fallback for environments that pass the object directly.
        if (isset($args['variation'])
            || isset($args['variationId'])
            || isset($args['item'])
            || isset($args['properties'])
            || isset($args['variationProperties'])) {
            return $args;
        }

        return [];
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
