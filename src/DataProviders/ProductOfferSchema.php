<?php

namespace FeedbackGeoFM\DataProviders;

use FeedbackGeoFM\Services\FeedbackService;
use FeedbackGeoFM\Services\VideoPropertyResolver;
use Plenty\Modules\Item\ItemShippingProfiles\Contracts\ItemShippingProfilesRepositoryContract;
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

        // The plentyShop item document does not reliably contain the shipping
        // profiles linked to the item. Resolve the authoritative item-level
        // relation server-side so exact profile prices can be applied even
        // when variation.defaultShippingCosts only contains a generic value.
        if ($itemId > 0) {
            $data = $this->appendItemShippingProfiles($data, $itemId);
        }

        $sellerName = trim((string)$this->configValue(
            $config,
            'schemaSellerName',
            'Four & More GmbH'
        ));

        $schemaOptions = [
            'schemaManufacturerName' => trim((string)$this->configValueAllowEmpty(
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
            'schemaShippingFallbackEnabled' => $this->configBool(
                $config,
                'schemaShippingFallbackEnabled',
                false
            ),
            'schemaShippingProfilePrices' => trim((string)$this->configValueAllowEmpty(
                $config,
                'schemaShippingProfilePrices',
                ''
            )),
            'schemaShippingPackagePrice' => trim((string)$this->configValueAllowEmpty(
                $config,
                'schemaShippingPackagePrice',
                ''
            )),
            'schemaShippingFreightPrice' => trim((string)$this->configValueAllowEmpty(
                $config,
                'schemaShippingFreightPrice',
                ''
            )),
            'schemaShippingFreightWeightThresholdKg' => max(0, (float)$this->configValue(
                $config,
                'schemaShippingFreightWeightThresholdKg',
                31.5
            )),
            'schemaShippingFreightProfileIds' => trim((string)$this->configValueAllowEmpty(
                $config,
                'schemaShippingFreightProfileIds',
                ''
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
            'schemaVideoObject' => false,
            // PlentyONE sometimes includes sibling item documents in the
            // layout-container arguments. The builder may use only these real
            // documents for ProductGroup.hasVariant; no variants are invented.
            'schemaVariantDocuments' => $this->resolveVariantDocuments($args, $itemId)
        ];

        // Resolve the per-product YouTube video directly from the same PlentyONE
        // variation properties used by the ShopBuilder video block. The defaults
        // match the Mephisto setup: property 110 = YouTube ID, property 158 =
        // upload date. Invalid or incomplete values simply produce no VideoObject.
        if ($this->configBool($config, 'schemaVideoFromProperties', true)) {
            $youtubePropertyIds = $this->configValue(
                $config,
                'schemaVideoYoutubePropertyId',
                110
            );
            $uploadDatePropertyIds = $this->configValue(
                $config,
                'schemaVideoUploadDatePropertyId',
                158
            );

            /** @var VideoPropertyResolver $videoResolver */
            $videoResolver = pluginApp(VideoPropertyResolver::class);
            $videoOptions = $videoResolver->resolve(
                $data,
                $youtubePropertyIds,
                $uploadDatePropertyIds
            );

            if (is_array($videoOptions)) {
                $schemaOptions = array_merge($schemaOptions, $videoOptions);
            }
        }

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
            JSON_UNESCAPED_SLASHES
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
     * Load the real item-to-shipping-profile links from PlentyONE. Shipping
     * profiles are assigned at item level, while the plentyShop/Ceres item
     * document may omit this relation entirely.
     *
     * Repository failures are intentionally non-fatal: the builder can still
     * fall back to variation.defaultShippingCosts and the legacy package/
     * freight rules.
     *
     * @param array $data
     * @param int $itemId
     * @return array
     */
    private function appendItemShippingProfiles(array $data, $itemId)
    {
        try {
            /** @var ItemShippingProfilesRepositoryContract $repository */
            $repository = pluginApp(ItemShippingProfilesRepositoryContract::class);
            $profiles = $repository->findByItemId((int)$itemId);

            if (is_array($profiles) && !empty($profiles)) {
                $data['itemShippingProfiles'] = $profiles;
            } elseif (is_object($profiles)) {
                $profilesArray = $this->toArray($profiles);
                if (!empty($profilesArray)) {
                    $data['itemShippingProfiles'] = $profilesArray;
                }
            }
        } catch (\Throwable $e) {
            // Keep schema rendering resilient if the repository is unavailable.
        }

        return $data;
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
     * Collect concrete sibling variation documents already present in the
     * layout-container payload. This remains deliberately local: the schema
     * renderer must not trigger an additional variation search for every page.
     *
     * @param mixed $args
     * @param int $itemId
     * @return array
     */
    private function resolveVariantDocuments($args, $itemId)
    {
        if ((int)$itemId <= 0) {
            return [];
        }

        $documents = [];
        $seenVariationIds = [];
        $this->collectVariantDocuments(
            $args,
            (int)$itemId,
            0,
            $documents,
            $seenVariationIds
        );

        return $documents;
    }

    /**
     * @param mixed $node
     * @param int $itemId
     * @param int $depth
     * @param array $documents
     * @param array $seenVariationIds
     * @return void
     */
    private function collectVariantDocuments(
        $node,
        $itemId,
        $depth,
        array &$documents,
        array &$seenVariationIds
    ) {
        if ($depth > 10 || count($documents) >= 50) {
            return;
        }

        $node = $this->toArray($node);
        if (empty($node)) {
            return;
        }

        $nodeItemId = $this->resolveId($node, 'item', 'id');
        $nodeVariationId = $this->resolveId($node, 'variation', 'id');
        if ($nodeItemId === (int)$itemId
            && $nodeVariationId > 0
            && !isset($seenVariationIds[$nodeVariationId])) {
            $seenVariationIds[$nodeVariationId] = true;
            $documents[] = $node;
        }

        foreach ($node as $child) {
            if (!is_array($child) && !is_object($child)) {
                continue;
            }

            $this->collectVariantDocuments(
                $child,
                $itemId,
                $depth + 1,
                $documents,
                $seenVariationIds
            );

            if (count($documents) >= 50) {
                break;
            }
        }
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
     * Returns a text setting while preserving an explicitly saved empty value.
     * This is required for the manufacturer setting, where an empty value
     * intentionally activates automatic resolution from the item document.
     *
     * @param ConfigRepository $config
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function configValueAllowEmpty(ConfigRepository $config, $key, $default)
    {
        $value = $config->get('FeedbackGeoFM.' . $key);
        return $value !== null ? $value : $default;
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
