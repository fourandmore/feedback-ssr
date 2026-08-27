<?php

namespace FeedbackGeoFM\DataProviders;

use FeedbackGeoFM\Services\FeedbackService;
use FeedbackGeoFM\Services\VideoPropertyResolver;
use IO\Services\ItemService;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\VariationAttributeMap;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
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
            'schemaVariantDocuments' => $this->resolveVariantDocumentsForSchema(
                $args,
                $data,
                $itemId
            )
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
     * Resolve sibling variant documents for ProductGroup.hasVariant.
     *
     * The SingleItem layout-container usually contains only the currently
     * selected document. For a non-salable main variation this means the
     * ProductGroup would otherwise have no hasVariant entries. On genuine
     * ProductGroup pages we therefore load the active and salable child
     * variations through IO's storefront-aware ItemService and merge them with
     * any documents already present in the container arguments.
     *
     * @param mixed $args
     * @param array $data
     * @param int $itemId
     * @return array
     */
    private function resolveVariantDocumentsForSchema($args, array $data, $itemId)
    {
        $documents = $this->resolveVariantDocuments($args, $itemId);

        if (!$this->isVariationGroupData($data) || (int)$itemId <= 0) {
            return $documents;
        }

        $seenVariationIds = [];
        foreach ($documents as $document) {
            $document = $this->toArray($document);
            $variationId = $this->resolveId($document, 'variation', 'id');
            if ($variationId > 0) {
                $seenVariationIds[$variationId] = true;
            }
        }

        try {
            /** @var ItemService $itemService */
            $itemService = pluginApp(ItemService::class);

            // Use the exact variation-map search preset behind Ceres'
            // /io/variations/map endpoint. In Ceres 5.0.81 the browser-side
            // variation selector is fed by VariationAttributeMapResource,
            // which no longer delegates to ItemService::getVariationAttributeMap().
            // Using the same search preset here keeps the server-rendered
            // ProductGroup in sync with the variants the shopper can select.
            $attributeMap = $this->loadVariationAttributeMap((int)$itemId);
            // IMPORTANT: determine IDs from the raw map first. Attribute-name
            // repository lookups are optional enrichment and must never be able
            // to collapse the whole ProductGroup to zero variants.
            $variationIds = $this->extractVariationIdsFromAttributeMap($attributeMap);

            // Compatibility fallback matching IO's long-standing storefront
            // service. This returns the same raw variationId/attributes shape
            // used by the selector in Ceres 5.x.
            if (empty($variationIds)) {
                $attributeMap = $itemService->getVariationAttributeMap((int)$itemId);
                $variationIds = $this->extractVariationIdsFromAttributeMap($attributeMap);
            }

            if (empty($variationIds)) {
                $variationIds = $itemService->getVariationList((int)$itemId, false);
                $variationIds = is_array($variationIds)
                    ? array_values(array_unique(array_map('intval', $variationIds)))
                    : [];
            }

            $variationIds = array_values(array_filter($variationIds, function ($variationId) use ($seenVariationIds) {
                $variationId = (int)$variationId;
                return $variationId > 0 && !isset($seenVariationIds[$variationId]);
            }));

            // 120 variants comfortably covers the current Four & More product
            // groups while keeping runaway schemas bounded.
            $variationIds = array_slice($variationIds, 0, max(0, 120 - count($documents)));
            if (empty($variationIds)) {
                return $documents;
            }

            // VariationList searches are paginated internally. Query in small
            // batches so every requested variation is returned instead of only
            // the first default result page.
            $loadedDocuments = [];
            $loadedSeen = [];
            foreach (array_chunk($variationIds, 20) as $variationIdChunk) {
                try {
                    $result = $itemService->getVariations($variationIdChunk);
                    $this->collectVariantDocuments(
                        $result,
                        (int)$itemId,
                        0,
                        $loadedDocuments,
                        $loadedSeen
                    );
                } catch (\Throwable $e) {
                    // Keep other chunks usable if a single storefront search
                    // fails for one batch.
                    continue;
                }
            }

            // Resolve human-readable attribute names only after the variation
            // documents have been loaded. Failures here are non-fatal: hasVariant
            // must still be emitted with Product/Offer data even if size/color
            // labels cannot be enriched in a particular Plenty patch level.
            $variantMetaById = $this->buildVariantMetaMap($attributeMap, $itemService);

            // Item shipping profiles are item-level relations. They were already
            // resolved once for the parent document, so reuse the exact same
            // relation for every child instead of querying the repository again.
            $itemShippingProfiles = isset($data['itemShippingProfiles'])
                ? $this->toArray($data['itemShippingProfiles'])
                : [];

            foreach ($loadedDocuments as $document) {
                $document = $this->toArray($document);
                $variationId = $this->resolveId($document, 'variation', 'id');
                if ($variationId <= 0 || isset($seenVariationIds[$variationId])) {
                    continue;
                }

                if (!empty($itemShippingProfiles)) {
                    $document['itemShippingProfiles'] = $itemShippingProfiles;
                }

                if (isset($variantMetaById[$variationId])) {
                    $document['feedbackVariantAttributes'] = $variantMetaById[$variationId];
                }

                $seenVariationIds[$variationId] = true;
                $documents[] = $document;
                if (count($documents) >= 120) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            // A ProductGroup without hasVariant is preferable to breaking the
            // item page if IO cannot resolve the child variations.
        }

        return $documents;
    }

    /**
     * Execute the same VariationAttributeMap search preset used by
     * IO\Api\Resources\VariationAttributeMapResource. Results are flattened
     * defensively because PlentyONE search result envelopes can differ between
     * IO patch releases. Composite-search pagination is followed via afterKey
     * when present, capped to avoid runaway server work.
     *
     * @param int $itemId
     * @return array
     */
    private function loadVariationAttributeMap($itemId)
    {
        if ((int)$itemId <= 0) {
            return [];
        }

        try {
            /** @var ItemSearchService $itemSearchService */
            $itemSearchService = pluginApp(ItemSearchService::class);

            $entries = [];
            $seenVariationIds = [];
            $afterKey = null;

            for ($page = 0; $page < 10 && count($entries) < 120; $page++) {
                $options = ['itemId' => (int)$itemId];
                if ($afterKey !== null && $afterKey !== '' && $afterKey !== []) {
                    $options['afterKey'] = $afterKey;
                }

                $searchFactory = VariationAttributeMap::getSearchFactory($options);
                $result = $itemSearchService->getResult($searchFactory);

                $this->collectVariationMapEntries(
                    $result,
                    $entries,
                    $seenVariationIds
                );

                $nextAfterKey = $this->findAfterKey($result);
                if ($nextAfterKey === null
                    || $nextAfterKey === ''
                    || $nextAfterKey === []
                    || $nextAfterKey === $afterKey) {
                    break;
                }

                $afterKey = $nextAfterKey;
            }

            return array_slice($entries, 0, 120);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param mixed $node
     * @param array $entries
     * @param array $seenVariationIds
     * @param int $depth
     * @return void
     */
    private function collectVariationMapEntries(
        $node,
        array &$entries,
        array &$seenVariationIds,
        $depth = 0
    ) {
        if ($depth > 12 || count($entries) >= 120) {
            return;
        }

        $node = $this->toArray($node);
        if (empty($node)) {
            return;
        }

        $variationId = isset($node['variationId']) && is_numeric($node['variationId'])
            ? (int)$node['variationId']
            : 0;

        if ($variationId > 0 && !isset($seenVariationIds[$variationId])) {
            $attributes = isset($node['attributes'])
                ? $this->toArray($node['attributes'])
                : [];

            if (array_key_exists('attributes', $node)) {
                $seenVariationIds[$variationId] = true;
                $entries[] = [
                    'variationId' => $variationId,
                    'attributes' => $attributes
                ];

                if (count($entries) >= 120) {
                    return;
                }
            }
        }

        foreach ($node as $child) {
            if (!is_array($child) && !is_object($child)) {
                continue;
            }

            $this->collectVariationMapEntries(
                $child,
                $entries,
                $seenVariationIds,
                $depth + 1
            );

            if (count($entries) >= 120) {
                break;
            }
        }
    }

    /**
     * Find a composite-search afterKey in an arbitrary PlentyONE result
     * envelope without depending on a specific patch-level response shape.
     *
     * @param mixed $node
     * @param int $depth
     * @return mixed|null
     */
    private function findAfterKey($node, $depth = 0)
    {
        if ($depth > 10) {
            return null;
        }

        $node = $this->toArray($node);
        if (empty($node)) {
            return null;
        }

        foreach (['afterKey', 'after_key'] as $key) {
            if (array_key_exists($key, $node)
                && $node[$key] !== null
                && $node[$key] !== ''
                && $node[$key] !== []) {
                return $node[$key];
            }
        }

        foreach ($node as $child) {
            if (!is_array($child) && !is_object($child)) {
                continue;
            }

            $found = $this->findAfterKey($child, $depth + 1);
            if ($found !== null && $found !== '' && $found !== []) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Extract variation IDs from the raw selector map without resolving any
     * localized attribute labels. This intentionally has no repository
     * dependencies so a failed attribute-name lookup can never suppress
     * ProductGroup.hasVariant.
     *
     * @param mixed $attributeMap
     * @return array<int>
     */
    private function extractVariationIdsFromAttributeMap($attributeMap)
    {
        $attributeMap = $this->toArray($attributeMap);
        if (empty($attributeMap)) {
            return [];
        }

        $ids = [];
        foreach ($attributeMap as $entry) {
            $entry = $this->toArray($entry);
            if (isset($entry['variationId']) && is_numeric($entry['variationId'])) {
                $variationId = (int)$entry['variationId'];
                if ($variationId > 0) {
                    $ids[$variationId] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * Resolve the same attribute/value information Ceres uses for its
     * variation selector and enrich it with localized names. ItemService caches
     * attribute and value name lookups internally, so repeated IDs are cheap.
     *
     * @param mixed $attributeMap
     * @param ItemService $itemService
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function buildVariantMetaMap($attributeMap, ItemService $itemService)
    {
        $attributeMap = $this->toArray($attributeMap);
        if (empty($attributeMap)) {
            return [];
        }

        $result = [];
        $attributeNames = [];
        $valueNames = [];

        foreach ($attributeMap as $entry) {
            $entry = $this->toArray($entry);
            $variationId = isset($entry['variationId']) && is_numeric($entry['variationId'])
                ? (int)$entry['variationId']
                : 0;
            if ($variationId <= 0) {
                continue;
            }

            $resolved = [];
            foreach ($this->toArray(isset($entry['attributes']) ? $entry['attributes'] : []) as $attribute) {
                $attribute = $this->toArray($attribute);
                $attributeId = isset($attribute['attributeId']) && is_numeric($attribute['attributeId'])
                    ? (int)$attribute['attributeId']
                    : 0;
                $attributeValueId = isset($attribute['attributeValueId']) && is_numeric($attribute['attributeValueId'])
                    ? (int)$attribute['attributeValueId']
                    : 0;
                if ($attributeId <= 0 || $attributeValueId <= 0) {
                    continue;
                }

                if (!array_key_exists($attributeId, $attributeNames)) {
                    try {
                        $attributeNames[$attributeId] = trim((string)$itemService->getAttributeName($attributeId));
                    } catch (\Throwable $e) {
                        $attributeNames[$attributeId] = '';
                    }
                }
                if (!array_key_exists($attributeValueId, $valueNames)) {
                    try {
                        $valueNames[$attributeValueId] = trim((string)$itemService->getAttributeValueName($attributeValueId));
                    } catch (\Throwable $e) {
                        $valueNames[$attributeValueId] = '';
                    }
                }

                if ($attributeNames[$attributeId] === '' || $valueNames[$attributeValueId] === '') {
                    continue;
                }

                $resolved[] = [
                    'attributeId' => $attributeId,
                    'attributeValueId' => $attributeValueId,
                    'name' => $attributeNames[$attributeId],
                    'value' => $valueNames[$attributeValueId]
                ];
            }

            if (!empty($resolved)) {
                $result[$variationId] = $resolved;
            }
        }

        return $result;
    }

    /**
     * Mirror the ProductSchemaBuilder's ProductGroup detection without making
     * the builder part of this data-provider's control flow.
     *
     * @param array $data
     * @return bool
     */
    private function isVariationGroupData(array $data)
    {
        $filter = isset($data['filter']) && is_array($data['filter'])
            ? $data['filter']
            : [];

        $hasChildren = $this->truthy(isset($filter['hasChildren']) ? $filter['hasChildren'] : false)
            || $this->truthy(isset($filter['hasActiveChildren']) ? $filter['hasActiveChildren'] : false);
        $hasSalableFlag = array_key_exists('isSalable', $filter);
        $isSalable = $hasSalableFlag ? $this->truthy($filter['isSalable']) : true;

        return $hasChildren && $hasSalableFlag && !$isSalable;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function truthy($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }
        return !empty($value);
    }

    /**
     * Collect concrete sibling variation documents already present in the
     * layout-container payload. ProductGroup pages may augment this local set
     * via resolveVariantDocumentsForSchema().
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
        if ($depth > 10 || count($documents) >= 120) {
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

            if (count($documents) >= 120) {
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
