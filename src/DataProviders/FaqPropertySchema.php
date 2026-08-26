<?php

namespace FeedbackGeoFM\DataProviders;

use FeedbackGeoFM\Services\FeedbackService;
use Plenty\Plugin\Templates\Twig;

/**
 * Server-side FAQPage JSON-LD provider for plentyShop single item views.
 *
 * Linked to Ceres::SingleItem.BeforeAddToBasket. plentyShop passes the current
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
     *                    Ceres::SingleItem.BeforeAddToBasket, $args[0] contains
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
            JSON_UNESCAPED_SLASHES
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
        $args = $this->toArray($args);
        if (empty($args)) {
            return [];
        }

        $candidates = [$args];
        if (isset($args[0])) {
            $candidates[] = $this->toArray($args[0]);
        }

        foreach ($candidates as $candidate) {
            if (isset($candidate['variation'])
                || isset($candidate['variationId'])
                || (isset($candidate['item'])
                    && is_array($candidate['item'])
                    && isset($candidate['item']['id']))
                || isset($candidate['properties'])
                || isset($candidate['variationProperties'])) {
                return $candidate;
            }

            if (isset($candidate['documents'][0]['data'])) {
                $documentData = $this->toArray($candidate['documents'][0]['data']);
                if (!empty($documentData)) {
                    return $documentData;
                }
            }

            if (isset($candidate['item']['documents'][0]['data'])) {
                $documentData = $this->toArray($candidate['item']['documents'][0]['data']);
                if (!empty($documentData)) {
                    return $documentData;
                }
            }

            if (isset($candidate['data'])) {
                $documentData = $this->toArray($candidate['data']);
                if (!empty($documentData)) {
                    return $documentData;
                }
            }
        }

        return [];
    }

    /**
     * @param mixed $value
     * @return array
     */
    private function toArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $encoded = json_encode($value);
            if ($encoded !== false) {
                $decoded = json_decode($encoded, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
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
