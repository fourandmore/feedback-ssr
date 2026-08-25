<?php

namespace FeedbackGeoFM\DataProviders;

use FeedbackGeoFM\Services\FeedbackService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Templates\Twig;

/**
 * Outputs Product/ProductGroup/Offer JSON-LD as a real server-side script.
 *
 * ShopBuilder rewrites script tags contained in widget templates. Keeping the
 * structured data in the non-deprecated SingleItem.BeforeAddToBasket layout
 * container ensures that crawlers receive valid application/ld+json in the
 * initial HTML response.
 */
class ProductOfferSchema
{
    /**
     * @param Twig $twig
     * @param mixed $args
     * @return string
     */
    public function call(Twig $twig, $args)
    {
        /** @var ConfigRepository $config */
        $config = pluginApp(ConfigRepository::class);

        if (!$this->configBool($config, 'schemaProductOfferEnabled', true)) {
            return '';
        }

        $data = $this->resolveItemData($args);
        if (empty($data)) {
            return '';
        }

        $itemId = $this->resolveId($data, 'item', 'id');
        $variationId = $this->resolveId($data, 'variation', 'id');

        if ($itemId <= 0 && $variationId <= 0) {
            return '';
        }

        $sellerName = trim((string)$this->configValue(
            $config,
            'schemaSellerName',
            'Four & More GmbH'
        ));

        $schemaOptions = [
            'schemaManufacturerName' => trim((string)$this->configValue(
                $config,
                'schemaManufacturerName',
                'Four & More GmbH'
            )),
            'schemaVariesBy' => trim((string)$this->configValue($config, 'schemaVariesBy', '')),
            'schemaShippingPolicy' => $this->configBool($config, 'schemaShippingPolicy', false),
            'schemaShippingCountries' => trim((string)$this->configValue(
                $config,
                'schemaShippingCountries',
                'DE'
            )),
            'schemaHandlingTimeMin' => max(0, (int)$this->configValue(
                $config,
                'schemaHandlingTimeMin',
                0
            )),
            'schemaHandlingTimeMax' => max(0, (int)$this->configValue(
                $config,
                'schemaHandlingTimeMax',
                1
            )),
            'schemaTransitTimeMin' => max(0, (int)$this->configValue(
                $config,
                'schemaTransitTimeMin',
                1
            )),
            'schemaTransitTimeMax' => max(0, (int)$this->configValue(
                $config,
                'schemaTransitTimeMax',
                3
            )),
            'schemaReturnPolicy' => $this->configBool($config, 'schemaReturnPolicy', true),
            'schemaReturnCountries' => trim((string)$this->configValue(
                $config,
                'schemaReturnCountries',
                'DE'
            )),
            'schemaReturnDays' => max(1, (int)$this->configValue(
                $config,
                'schemaReturnDays',
                14
            )),
            'schemaReturnPolicyUrl' => trim((string)$this->configValue(
                $config,
                'schemaReturnPolicyUrl',
                ''
            )),
            // Per-product video data cannot be represented safely by global
            // plugin settings and remains disabled in the DataProvider.
            'schemaVideoObject' => false
        ];

        /** @var FeedbackService $feedbackService */
        $feedbackService = pluginApp(FeedbackService::class);
        $initialData = $feedbackService->getInitialData(
            $itemId,
            $variationId,
            10,
            $data,
            true,
            $sellerName,
            $schemaOptions
        );

        if (!is_array($initialData)
            || !isset($initialData['jsonLd'])
            || !is_array($initialData['jsonLd'])
            || empty($initialData['jsonLd']['@type'])) {
            return '';
        }

        $json = json_encode(
            $initialData['jsonLd'],
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

        return '<script id="feedback-product-offer-jsonld" type="application/ld+json">'
            . $json
            . '</script>';
    }

    /**
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
                || (isset($candidate['item'])
                    && is_array($candidate['item'])
                    && isset($candidate['item']['id']))) {
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
                if (isset($documentData['variation']) || isset($documentData['item'])) {
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
     * @param string $root
     * @param string $key
     * @return int
     */
    private function resolveId(array $data, $root, $key)
    {
        if (isset($data[$root])
            && is_array($data[$root])
            && isset($data[$root][$key])
            && is_numeric($data[$root][$key])) {
            return (int)$data[$root][$key];
        }

        return -1;
    }

    /**
     * @param ConfigRepository $config
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function configValue(ConfigRepository $config, $key, $default)
    {
        $value = $config->get('FeedbackGeoFM.' . $key);
        return $value !== null && $value !== '' ? $value : $default;
    }

    /**
     * @param ConfigRepository $config
     * @param string $key
     * @param bool $default
     * @return bool
     */
    private function configBool(ConfigRepository $config, $key, $default)
    {
        $value = $config->get('FeedbackGeoFM.' . $key);
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value !== 0;
        }

        return in_array(strtolower(trim((string)$value)), ['true', 'yes', 'on'], true);
    }
}
