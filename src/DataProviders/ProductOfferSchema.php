<?php

namespace FeedbackGeoFM\DataProviders;

use FeedbackGeoFM\Services\FeedbackService;
use FeedbackGeoFM\Services\VideoPropertyResolver;
use IO\Services\ItemService;
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

        $variantDiagnostics = [];
        $variantDocuments = $this->resolveVariantDocumentsForSchema(
            $args,
            $data,
            $itemId,
            $variantDiagnostics
        );

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
            'schemaVariantDocuments' => $variantDocuments
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

        $diagnosticJson = json_encode(
            $variantDiagnostics,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        $diagnosticScript = '';
        if (is_string($diagnosticJson) && $diagnosticJson !== '') {
            $diagnosticScript = '<script id="feedback-geofm-variant-diagnostics" type="application/json">'
                . $diagnosticJson
                . '</script>';
        }

        return $diagnosticScript
            . '<script id="feedback-product-offer-jsonld" type="application/ld+json">'
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
    private function resolveVariantDocumentsForSchema($args, array $data, $itemId, array &$diagnostics = [])
    {
        $documents = $this->resolveVariantDocuments($args, $itemId);
        $contextVariations = isset($data['feedbackGeoFMContextVariations'])
            ? $this->toArray($data['feedbackGeoFMContextVariations'])
            : [];
        $contextAttributes = isset($data['feedbackGeoFMContextAttributes'])
            ? $this->toArray($data['feedbackGeoFMContextAttributes'])
            : [];
        $contextAfterKey = isset($data['feedbackGeoFMContextAfterKey'])
            ? $this->toArray($data['feedbackGeoFMContextAfterKey'])
            : [];

        $diagnostics = [
            'pluginVersion' => '5.0.52',
            'diagnosticOnly' => true,
            'itemId' => (int)$itemId,
            'isVariationGroup' => $this->isVariationGroupData($data),
            'contextVariationsCount' => count($contextVariations),
            'contextAttributesCount' => count($contextAttributes),
            'contextAfterKeyCount' => count($contextAfterKey),
            'contextFirstVariationKeys' => $this->firstArrayKeys($contextVariations),
            'contextFirstAttributeKeys' => $this->firstArrayKeys($contextAttributes),
            'existingContainerDocumentsCount' => count($documents),
            'salableVariationIdsCount' => 0,
            'salableVariationIdsSample' => [],
            'getVariationsCalled' => false,
            'getVariationsInputCount' => 0,
            'getVariationsInputSample' => [],
            'getVariationsResultType' => null,
            'getVariationsResultCount' => null,
            'getVariationsTopLevelKeys' => [],
            'getVariationsFirstKey' => null,
            'getVariationsFirstValueType' => null,
            'getVariationsFirstValueKeys' => [],
            'getVariationsFirstValuePreview' => null,
            'collectorDocumentsCount' => 0,
            'collectorVariationIdSample' => [],
            'errorClass' => null,
            'errorMessage' => null
        ];

        if (!$diagnostics['isVariationGroup'] || (int)$itemId <= 0 || empty($contextVariations)) {
            return $documents;
        }

        $variationIds = $this->extractSalableVariationIds($contextVariations);
        $diagnostics['salableVariationIdsCount'] = count($variationIds);
        $diagnostics['salableVariationIdsSample'] = array_slice(array_values($variationIds), 0, 10);

        if (empty($variationIds)) {
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

        $variationIds = array_values(array_filter(
            $variationIds,
            function ($variationId) use ($seenVariationIds) {
                $variationId = (int)$variationId;
                return $variationId > 0 && !isset($seenVariationIds[$variationId]);
            }
        ));

        // Diagnostic request only: use a small sample with the documented
        // one-argument ItemService::getVariations(array $variationIds) API.
        // This deliberately avoids guessing limits or undocumented parameters.
        $diagnosticVariationIds = array_slice($variationIds, 0, 10);
        $diagnostics['getVariationsInputCount'] = count($diagnosticVariationIds);
        $diagnostics['getVariationsInputSample'] = $diagnosticVariationIds;

        if (empty($diagnosticVariationIds)) {
            return $documents;
        }

        try {
            /** @var ItemService $itemService */
            $itemService = pluginApp(ItemService::class);
            $diagnostics['getVariationsCalled'] = true;

            $result = $itemService->getVariations($diagnosticVariationIds);

            $diagnostics['getVariationsResultType'] = gettype($result);
            if (is_array($result)) {
                $diagnostics['getVariationsResultCount'] = count($result);
                $diagnostics['getVariationsTopLevelKeys'] = array_slice(array_keys($result), 0, 30);

                if (!empty($result)) {
                    $firstKey = array_key_first($result);
                    $firstValue = $result[$firstKey];
                    $firstValueArray = $this->toArray($firstValue);

                    $diagnostics['getVariationsFirstKey'] = $firstKey;
                    $diagnostics['getVariationsFirstValueType'] = gettype($firstValue);
                    $diagnostics['getVariationsFirstValueKeys'] = is_array($firstValueArray)
                        ? array_slice(array_keys($firstValueArray), 0, 50)
                        : [];
                    $diagnostics['getVariationsFirstValuePreview'] = $this->jsonPreview($firstValue, 2500);
                }
            } else {
                $diagnostics['getVariationsFirstValuePreview'] = $this->jsonPreview($result, 2500);
            }

            $loadedDocuments = [];
            $loadedSeen = [];
            $this->collectVariantDocuments(
                $result,
                (int)$itemId,
                0,
                $loadedDocuments,
                $loadedSeen
            );

            $diagnostics['collectorDocumentsCount'] = count($loadedDocuments);
            $diagnostics['collectorVariationIdSample'] = array_slice(array_map(
                'intval',
                array_keys($loadedSeen)
            ), 0, 10);

            // Diagnostic version: do not change ProductGroup.hasVariant yet.
            // We first observe the exact documented API response in the live shop.
        } catch (\Throwable $e) {
            $diagnostics['errorClass'] = get_class($e);
            $diagnostics['errorMessage'] = $e->getMessage();
        }

        return $documents;
    }

    /**
     * Return the keys of the first array/object entry without assuming a
     * particular PlentyONE response shape.
     *
     * @param mixed $values
     * @return array
     */
    private function firstArrayKeys($values)
    {
        $values = $this->toArray($values);
        if (empty($values)) {
            return [];
        }

        $firstKey = array_key_first($values);
        $firstValue = $this->toArray($values[$firstKey]);
        return empty($firstValue) ? [] : array_slice(array_keys($firstValue), 0, 50);
    }

    /**
     * Serialize a bounded diagnostic preview. This is not schema data and is
     * emitted only in the application/json diagnostic script.
     *
     * @param mixed $value
     * @param int $maxLength
     * @return string|null
     */
    private function jsonPreview($value, $maxLength = 2500)
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if (!is_string($json)) {
            return null;
        }

        if (strlen($json) <= $maxLength) {
            return $json;
        }

        return substr($json, 0, $maxLength) . '...';
    }

    /**
     * Extract active/salable variation IDs from the documented Ceres
     * SingleItemContext variation map. The map may be nested, so traversal is
     * recursive and deliberately ignores unrelated numeric values.
     *
     * @param mixed $node
     * @param int $depth
     * @param array $ids
     * @return array<int>
     */
    private function extractSalableVariationIds($node, $depth = 0, array &$ids = [])
    {
        if ($depth > 12 || count($ids) >= 120) {
            return array_keys($ids);
        }

        $node = $this->toArray($node);
        if (empty($node)) {
            return array_keys($ids);
        }

        if (isset($node['variationId']) && is_numeric($node['variationId'])) {
            $variationId = (int)$node['variationId'];
            $isSalable = !array_key_exists('isSalable', $node)
                || $this->truthy($node['isSalable']);

            if ($variationId > 0 && $isSalable) {
                $ids[$variationId] = true;
            }
        }

        foreach ($node as $child) {
            if (!is_array($child) && !is_object($child)) {
                continue;
            }
            $this->extractSalableVariationIds($child, $depth + 1, $ids);
            if (count($ids) >= 120) {
                break;
            }
        }

        return array_keys($ids);
    }

    /**
     * Resolve localized attribute/value names for the selector entries with
     * documented ItemService methods. These values are only schema enrichment;
     * failure of a single lookup never suppresses the underlying Product.
     *
     * @param mixed $variationMap
     * @param ItemService $itemService
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function buildVariantMetaMap($variationMap, ItemService $itemService)
    {
        $entries = [];
        $seen = [];
        $this->collectVariationEntries($variationMap, 0, $entries, $seen);

        if (empty($entries)) {
            return [];
        }

        $result = [];
        $attributeNames = [];
        $valueNames = [];

        foreach ($entries as $entry) {
            $variationId = (int)$entry['variationId'];
            $resolved = [];

            foreach ($this->toArray($entry['attributes']) as $attribute) {
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
     * Flatten variation selector entries while preserving only the documented
     * variationId/attributes structure.
     *
     * @param mixed $node
     * @param int $depth
     * @param array $entries
     * @param array $seen
     * @return void
     */
    private function collectVariationEntries($node, $depth, array &$entries, array &$seen)
    {
        if ($depth > 12 || count($entries) >= 120) {
            return;
        }

        $node = $this->toArray($node);
        if (empty($node)) {
            return;
        }

        if (isset($node['variationId']) && is_numeric($node['variationId'])) {
            $variationId = (int)$node['variationId'];
            if ($variationId > 0 && !isset($seen[$variationId])) {
                $seen[$variationId] = true;
                $entries[] = [
                    'variationId' => $variationId,
                    'attributes' => isset($node['attributes'])
                        ? $this->toArray($node['attributes'])
                        : []
                ];
            }
        }

        foreach ($node as $child) {
            if (!is_array($child) && !is_object($child)) {
                continue;
            }
            $this->collectVariationEntries($child, $depth + 1, $entries, $seen);
            if (count($entries) >= 120) {
                break;
            }
        }
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
