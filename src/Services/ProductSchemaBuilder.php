<?php

namespace Feedback\Services;

/**
 * Builds a cache-safe Schema.org Product/Offer object from the current
 * plentyShop item document. The class deliberately has no PlentyONE
 * dependencies so it can be tested independently.
 */
class ProductSchemaBuilder
{
    /**
     * @param mixed $itemData
     * @param string $canonicalUrl
     * @param array $counts
     * @param array $reviews
     * @param string $sellerName
     * @param array $schemaOptions
     * @return array|null
     */
    public function build(
        $itemData,
        $canonicalUrl,
        array $counts = [],
        array $reviews = [],
        $sellerName = '',
        array $schemaOptions = []
    ) {
        $data = $this->toArray($itemData);
        if (empty($data)) {
            return null;
        }

        $name = $this->firstText($data, [
            'texts.name1',
            'texts.name2',
            'texts.name3',
            'variation.name',
            'variation.model'
        ]);

        $priceData = $this->resolvePrice($data);
        $currency = strtoupper(trim((string)$priceData['currency']));
        $price = $priceData['price'];

        if ($name === '' || $price === null || $price <= 0 || $currency === '') {
            return null;
        }

        $canonicalUrl = trim((string)$canonicalUrl);
        $variationId = (int)$this->value($data, 'variation.id', 0);
        $itemId = (int)$this->value($data, 'item.id', 0);

        $productId = $canonicalUrl !== ''
            ? rtrim($canonicalUrl, '/') . '#product'
            : ($variationId > 0 ? 'variation-' . $variationId : 'item-' . $itemId);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $productId,
            'name' => $name
        ];

        if ($canonicalUrl !== '') {
            $schema['url'] = $canonicalUrl;
        }

        $description = $this->firstText($data, [
            'texts.shortDescription',
            'texts.metaDescription',
            'texts.description'
        ]);
        if ($description !== '') {
            $schema['description'] = $description;
        }

        $sku = $this->firstRaw($data, [
            'variation.number',
            'variation.externalId'
        ]);
        if ($sku !== '') {
            $schema['sku'] = $sku;
        }

        if ($itemId > 0) {
            $schema['productID'] = (string)$itemId;
        }

        $model = $this->firstText($data, ['variation.model']);
        if ($model !== '') {
            $schema['model'] = $model;
            $schema['mpn'] = $model;
        }

        $brand = $this->firstText($data, [
            'item.manufacturer.externalName',
            'item.manufacturer.legalName',
            'item.manufacturer.name'
        ]);
        if ($brand !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brand
            ];
        }

        $category = $this->resolveCategory($data, $canonicalUrl);
        if ($category !== '') {
            $schema['category'] = $category;
        }

        $images = $this->resolveImages($data, $canonicalUrl);
        if (!empty($images)) {
            $schema['image'] = $images;
        }

        $barcode = $this->resolveBarcode($data);
        if ($barcode !== null) {
            $schema[$barcode['property']] = $barcode['value'];
        }

        $offer = [
            '@type' => 'Offer',
            '@id' => ($canonicalUrl !== '' ? rtrim($canonicalUrl, '/') : $productId) . '#offer',
            'price' => number_format((float)$price, 2, '.', ''),
            'priceCurrency' => $currency
        ];

        $availability = $this->resolveAvailability($data);
        if ($availability !== null) {
            $offer['availability'] = $availability;
        }

        $itemCondition = $this->resolveItemCondition($data);
        if ($itemCondition !== null) {
            $offer['itemCondition'] = $itemCondition;
        }

        if ($canonicalUrl !== '') {
            $offer['url'] = $canonicalUrl;
        }

        $seller = $this->buildSellerOrganization(
            $sellerName,
            $canonicalUrl,
            $schemaOptions
        );
        if ($seller !== null) {
            $offer['seller'] = $seller;
        }

        $shippingDetails = $this->resolveShippingDetails(
            $data,
            $currency,
            $schemaOptions
        );
        if ($shippingDetails !== null) {
            $offer['shippingDetails'] = $shippingDetails;
        }

        $schema['offers'] = $offer;

        $ratingCount = isset($counts['ratingsCountTotal']) ? (int)$counts['ratingsCountTotal'] : 0;
        $ratingValue = isset($counts['averageValue']) ? (float)$counts['averageValue'] : 0.0;

        if ($ratingCount > 0 && $ratingValue > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($ratingValue, 2),
                'reviewCount' => $ratingCount,
                'bestRating' => 5,
                'worstRating' => 1
            ];
        }

        if (!empty($reviews)) {
            $schema['review'] = array_values($reviews);
        }

        $videoObject = $this->resolveVideoObject(
            $schemaOptions,
            $canonicalUrl,
            $name,
            $description
        );
        if ($videoObject !== null) {
            // A VideoObject is a CreativeWork about the Product. `subjectOf`
            // preserves the Product as the main entity while connecting the video.
            $schema['subjectOf'] = $videoObject;
        }

        return $schema;
    }

    /**
     * @param array $data
     * @return array{price: float|null, currency: string}
     */
    private function resolvePrice(array $data)
    {
        $candidates = [
            [
                'price' => 'prices.specialOffer.unitPrice.value',
                'currency' => 'prices.specialOffer.currency'
            ],
            [
                'price' => 'prices.default.unitPrice.value',
                'currency' => 'prices.default.currency'
            ],
            [
                'price' => 'prices.default.price.value',
                'currency' => 'prices.default.currency'
            ],
            [
                'price' => 'calculatedPrices.default.unitPrice',
                'currency' => 'calculatedPrices.default.currency'
            ],
            [
                'price' => 'calculatedPrices.default.price',
                'currency' => 'calculatedPrices.default.currency'
            ]
        ];

        foreach ($candidates as $candidate) {
            $value = $this->numericValue($this->value($data, $candidate['price'], null));
            if ($value !== null && $value > 0) {
                $currency = $this->value($data, $candidate['currency'], '');
                if ($currency === '') {
                    $currency = $this->value($data, 'prices.default.currency', '');
                }

                return [
                    'price' => $value,
                    'currency' => (string)$currency
                ];
            }
        }

        return [
            'price' => null,
            'currency' => ''
        ];
    }

    /**
     * @param array $data
     * @return string|null
     */
    private function resolveAvailability(array $data)
    {
        $isSalable = $this->value($data, 'filter.isSalable', null);

        if ($isSalable !== null) {
            return $this->toBool($isSalable)
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock';
        }

        return null;
    }

    /**
     * Map the PlentyONE item condition to Schema.org without guessing when the
     * condition is unknown.
     *
     * @param array $data
     * @return string|null
     */
    private function resolveItemCondition(array $data)
    {
        $condition = $this->firstText($data, [
            'item.condition.names.name',
            'item.condition.name',
            'condition.names.name',
            'condition.name'
        ]);

        if ($condition === '') {
            return null;
        }

        $normalized = strtolower($condition);
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);

        if (strpos($normalized, 'neu') !== false || strpos($normalized, 'new') !== false) {
            return 'https://schema.org/NewCondition';
        }

        if (strpos($normalized, 'general') !== false
            || strpos($normalized, 'refurb') !== false
            || strpos($normalized, 'b-ware') !== false
            || strpos($normalized, 'b ware') !== false) {
            return 'https://schema.org/RefurbishedCondition';
        }

        if (strpos($normalized, 'gebraucht') !== false || strpos($normalized, 'used') !== false) {
            return 'https://schema.org/UsedCondition';
        }

        if (strpos($normalized, 'beschaedigt') !== false || strpos($normalized, 'damaged') !== false) {
            return 'https://schema.org/DamagedCondition';
        }

        return null;
    }

    /**
     * Resolve a human-readable Product.category from category data and use the
     * canonical category path only as a final deterministic fallback.
     *
     * @param array $data
     * @param string $canonicalUrl
     * @return string
     */
    private function resolveCategory(array $data, $canonicalUrl)
    {
        $direct = $this->firstText($data, [
            'defaultCategory.details.0.name',
            'defaultCategory.details.name',
            'defaultCategory.name',
            'category.details.0.name',
            'category.details.name',
            'category.name',
            'variation.defaultCategory.details.0.name',
            'variation.defaultCategory.name',
            'item.defaultCategory.details.0.name',
            'item.defaultCategory.name'
        ]);
        if ($direct !== '') {
            return $direct;
        }

        // The canonical product URL normally contains the active storefront
        // category and is safer than joining several unrelated category links.
        $path = parse_url((string)$canonicalUrl, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
            if (count($segments) >= 2) {
                array_pop($segments);
                $fallback = [];
                foreach ($segments as $segment) {
                    $segment = rawurldecode($segment);
                    $segment = preg_replace('/[-_]+/', ' ', $segment);
                    $segment = trim((string)$segment);
                    if ($segment !== '') {
                        $fallback[] = ucwords($segment);
                    }
                }

                if (!empty($fallback)) {
                    return implode(' > ', $fallback);
                }
            }
        }

        $roots = [
            $this->value($data, 'categories', []),
            $this->value($data, 'defaultCategories', []),
            $this->value($data, 'variation.categories', []),
            $this->value($data, 'item.categories', [])
        ];

        $names = [];
        foreach ($roots as $root) {
            foreach ($this->collectCategoryNames($root) as $categoryName) {
                if (!in_array($categoryName, $names, true)) {
                    $names[] = $categoryName;
                }
            }
        }

        return empty($names) ? '' : implode(' > ', $names);
    }

    /**
     * @param mixed $value
     * @return array
     */
    private function collectCategoryNames($value)
    {
        $value = $this->toArray($value);
        if (empty($value)) {
            return [];
        }

        $names = [];

        foreach (['name', 'name1', 'nameInternal'] as $key) {
            if (array_key_exists($key, $value)) {
                $name = $this->cleanText($value[$key]);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        foreach ($value as $child) {
            if (is_array($child) || is_object($child)) {
                foreach ($this->collectCategoryNames($child) as $name) {
                    if (!in_array($name, $names, true)) {
                        $names[] = $name;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * @param string $sellerName
     * @param string $canonicalUrl
     * @param array $schemaOptions
     * @return array|null
     */
    private function buildSellerOrganization($sellerName, $canonicalUrl, array $schemaOptions)
    {
        $sellerName = $this->cleanText($sellerName);
        if ($sellerName === '') {
            return null;
        }

        $origin = $this->originUrl($canonicalUrl);
        $seller = [
            '@type' => 'Organization',
            'name' => $sellerName
        ];

        if ($origin !== '') {
            $seller['@id'] = rtrim($origin, '/') . '/#organization';
            $seller['url'] = rtrim($origin, '/') . '/';
        }

        if ($this->optionBool($schemaOptions, 'schemaReturnPolicy', true)) {
            $returnPolicy = $this->resolveReturnPolicy($schemaOptions, $origin);
            if ($returnPolicy !== null) {
                $seller['hasMerchantReturnPolicy'] = $returnPolicy;
            }
        }

        return $seller;
    }

    /**
     * @param array $schemaOptions
     * @param string $origin
     * @return array|null
     */
    private function resolveReturnPolicy(array $schemaOptions, $origin)
    {
        $countries = $this->parseCountryCodes(
            $this->option($schemaOptions, 'schemaReturnCountries', 'DE')
        );
        $days = max(1, (int)$this->option($schemaOptions, 'schemaReturnDays', 14));
        $link = trim((string)$this->option(
            $schemaOptions,
            'schemaReturnPolicyUrl',
            $origin !== '' ? rtrim($origin, '/') . '/widerrufsrecht/' : ''
        ));
        $link = $this->absoluteUrl($link, $origin);

        if (empty($countries) && $link === '') {
            return null;
        }

        $policy = [
            '@type' => 'MerchantReturnPolicy',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $days,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility',
            'refundType' => 'https://schema.org/FullRefund'
        ];

        if ($origin !== '') {
            $policy['@id'] = rtrim($origin, '/') . '/#merchant-return-policy';
        }

        if (!empty($countries)) {
            $policy['applicableCountry'] = count($countries) === 1 ? $countries[0] : $countries;
            $policy['returnPolicyCountry'] = $countries[0];
        }

        if ($link !== '') {
            $policy['merchantReturnLink'] = $link;
        }

        return $policy;
    }

    /**
     * Add product-specific OfferShippingDetails. PlentyONE documents
     * variation.defaultShippingCosts in the item document, so the amount stays
     * product-specific instead of being hard-coded in the schema builder.
     *
     * @param array $data
     * @param string $currency
     * @param array $schemaOptions
     * @return array|null
     */
    private function resolveShippingDetails(array $data, $currency, array $schemaOptions)
    {
        if (!$this->optionBool($schemaOptions, 'schemaShippingPolicy', true)) {
            return null;
        }

        $shippingCost = null;
        foreach ([
            'variation.defaultShippingCosts',
            'variation.defaultShippingCost',
            'shipping.defaultShippingCosts',
            'shipping.costs'
        ] as $path) {
            $shippingCost = $this->numericValue($this->value($data, $path, null));
            if ($shippingCost !== null && $shippingCost >= 0) {
                break;
            }
        }

        if ($shippingCost === null || $shippingCost < 0) {
            return null;
        }

        $countries = $this->parseCountryCodes(
            $this->option($schemaOptions, 'schemaShippingCountries', 'DE')
        );
        if (empty($countries)) {
            return null;
        }

        $destinations = [];
        foreach ($countries as $country) {
            $destinations[] = [
                '@type' => 'DefinedRegion',
                'addressCountry' => $country
            ];
        }

        $handlingMin = max(0, (int)$this->option($schemaOptions, 'schemaHandlingTimeMin', 0));
        $handlingMax = max($handlingMin, (int)$this->option($schemaOptions, 'schemaHandlingTimeMax', 1));
        $transitMin = max(0, (int)$this->option($schemaOptions, 'schemaTransitTimeMin', 1));
        $transitMax = max($transitMin, (int)$this->option($schemaOptions, 'schemaTransitTimeMax', 3));

        return [
            '@type' => 'OfferShippingDetails',
            'shippingDestination' => count($destinations) === 1 ? $destinations[0] : $destinations,
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => round($shippingCost, 2),
                'currency' => strtoupper((string)$currency)
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => $handlingMin,
                    'maxValue' => $handlingMax,
                    'unitCode' => 'DAY'
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => $transitMin,
                    'maxValue' => $transitMax,
                    'unitCode' => 'DAY'
                ]
            ]
        ];
    }

    /**
     * VideoObject is optional and only emitted when Google's required fields
     * are present. This prevents incomplete video markup on products without a
     * configured video.
     *
     * @param array $schemaOptions
     * @param string $canonicalUrl
     * @param string $productName
     * @param string $productDescription
     * @return array|null
     */
    private function resolveVideoObject(
        array $schemaOptions,
        $canonicalUrl,
        $productName,
        $productDescription
    ) {
        if (!$this->optionBool($schemaOptions, 'schemaVideoObject', false)) {
            return null;
        }

        $embedUrl = $this->absoluteUrl(
            $this->option($schemaOptions, 'schemaVideoEmbedUrl', ''),
            $canonicalUrl
        );
        $thumbnailUrl = $this->absoluteUrl(
            $this->option($schemaOptions, 'schemaVideoThumbnailUrl', ''),
            $canonicalUrl
        );
        if ($thumbnailUrl === '') {
            $thumbnailUrl = $this->youtubeThumbnail($embedUrl);
        }

        $uploadDate = trim((string)$this->option($schemaOptions, 'schemaVideoUploadDate', ''));
        if ($uploadDate !== '' && strtotime($uploadDate) === false) {
            $uploadDate = '';
        }

        if ($embedUrl === '' || $thumbnailUrl === '' || $uploadDate === '') {
            return null;
        }

        $videoName = $this->cleanText($this->option($schemaOptions, 'schemaVideoName', ''));
        if ($videoName === '') {
            $videoName = $productName . ' – Produktvideo';
        }

        $video = [
            '@type' => 'VideoObject',
            'name' => $videoName,
            'thumbnailUrl' => $thumbnailUrl,
            'uploadDate' => $uploadDate,
            'embedUrl' => $embedUrl
        ];

        if ($canonicalUrl !== '') {
            $video['@id'] = rtrim($canonicalUrl, '/') . '#video';
        }

        $description = $this->cleanText($this->option($schemaOptions, 'schemaVideoDescription', ''));
        if ($description === '') {
            $description = $productDescription;
        }
        if ($description !== '') {
            $video['description'] = $description;
        }

        $duration = strtoupper(trim((string)$this->option($schemaOptions, 'schemaVideoDuration', '')));
        if ($duration !== '' && preg_match('/^P(?:\d+D)?(?:T(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d+)?S)?)?$/', $duration) === 1) {
            $video['duration'] = $duration;
        }

        return $video;
    }

    /**
     * @param string $url
     * @return string
     */
    private function youtubeThumbnail($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $patterns = [
            '/youtube(?:-nocookie)?\.com\/embed\/([A-Za-z0-9_-]{6,})/i',
            '/youtube\.com\/watch\?[^#]*v=([A-Za-z0-9_-]{6,})/i',
            '/youtu\.be\/([A-Za-z0-9_-]{6,})/i'
        ];

        foreach ($patterns as $pattern) {
            $matches = [];
            if (preg_match($pattern, $url, $matches) === 1 && !empty($matches[1])) {
                return 'https://i.ytimg.com/vi/' . $matches[1] . '/hqdefault.jpg';
            }
        }

        return '';
    }

    /**
     * @param array $data
     * @param string $canonicalUrl
     * @return array
     */
    private function resolveImages(array $data, $canonicalUrl)
    {
        $images = [];
        $imageLists = [
            $this->value($data, 'images.variation', []),
            $this->value($data, 'images.all', [])
        ];

        foreach ($imageLists as $imageList) {
            if (is_object($imageList)) {
                $imageList = $this->toArray($imageList);
            }

            if (!is_array($imageList)) {
                continue;
            }

            foreach ($imageList as $image) {
                if (count($images) >= 10) {
                    break 2;
                }

                if (is_string($image)) {
                    $url = trim($image);
                } else {
                    $image = $this->toArray($image);
                    $url = $this->firstRaw($image, ['url', 'urlMiddle', 'urlPreview']);
                }

                $url = $this->absoluteUrl($url, $canonicalUrl);
                if ($url !== '' && !in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    /**
     * @param array $data
     * @return array|null
     */
    private function resolveBarcode(array $data)
    {
        $barcodes = $this->value($data, 'barcodes', []);
        if (is_object($barcodes)) {
            $barcodes = $this->toArray($barcodes);
        }

        if (!is_array($barcodes)) {
            return null;
        }

        foreach ($barcodes as $barcode) {
            $barcode = $this->toArray($barcode);
            $code = $this->firstRaw($barcode, ['code', 'barcode', 'value']);
            $digits = preg_replace('/\D+/', '', $code);

            $propertyByLength = [
                8 => 'gtin8',
                12 => 'gtin12',
                13 => 'gtin13',
                14 => 'gtin14'
            ];

            $length = strlen($digits);
            if (isset($propertyByLength[$length])) {
                return [
                    'property' => $propertyByLength[$length],
                    'value' => $digits
                ];
            }
        }

        return null;
    }

    /**
     * @param mixed $data
     * @param array $paths
     * @return string
     */
    private function firstText($data, array $paths)
    {
        foreach ($paths as $path) {
            $text = $this->cleanText($this->value($data, $path, ''));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @param mixed $data
     * @param array $paths
     * @return string
     */
    private function firstRaw($data, array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->value($data, $path, '');
            if (!is_array($value) && !is_object($value) && $value !== null) {
                $value = trim((string)$value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param mixed $data
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    private function value($data, $path, $default = null)
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return $default;
        }

        return $current;
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
     * @param mixed $value
     * @return string
     */
    private function cleanText($value)
    {
        if (is_array($value) || is_object($value) || $value === null) {
            return '';
        }

        $text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    private function numericValue($value)
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9,.-]+/', '', $value);
        if (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value !== 0;
        }

        return in_array(strtolower(trim((string)$value)), ['true', 'yes', 'on'], true);
    }

    /**
     * @param array $options
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function option(array $options, $key, $default = null)
    {
        return array_key_exists($key, $options) && $options[$key] !== null
            ? $options[$key]
            : $default;
    }

    /**
     * @param array $options
     * @param string $key
     * @param bool $default
     * @return bool
     */
    private function optionBool(array $options, $key, $default)
    {
        return $this->toBool($this->option($options, $key, $default));
    }

    /**
     * @param mixed $value
     * @return array
     */
    private function parseCountryCodes($value)
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[,;\s]+/', strtoupper(trim((string)$value)));
        }

        $countries = [];
        foreach ($parts as $part) {
            $country = strtoupper(trim((string)$part));
            if (preg_match('/^[A-Z]{2}$/', $country) === 1 && !in_array($country, $countries, true)) {
                $countries[] = $country;
            }
        }

        return $countries;
    }

    /**
     * @param string $url
     * @return string
     */
    private function originUrl($url)
    {
        $scheme = parse_url((string)$url, PHP_URL_SCHEME);
        $host = parse_url((string)$url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return '';
        }

        return (is_string($scheme) && $scheme !== '' ? $scheme : 'https') . '://' . $host;
    }

    /**
     * @param string $url
     * @param string $canonicalUrl
     * @return string
     */
    private function absoluteUrl($url, $canonicalUrl)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $scheme = parse_url($canonicalUrl, PHP_URL_SCHEME);
            return ($scheme !== null && $scheme !== '' ? $scheme : 'https') . ':' . $url;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $scheme = parse_url($canonicalUrl, PHP_URL_SCHEME);
        $host = parse_url($canonicalUrl, PHP_URL_HOST);
        if ($host !== null && $host !== '') {
            return ($scheme !== null && $scheme !== '' ? $scheme : 'https') . '://' . $host . '/' . ltrim($url, '/');
        }

        return $url;
    }
}
