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
     * @return array|null
     */
    public function build($itemData, $canonicalUrl, array $counts = [], array $reviews = [], $sellerName = '')
    {
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

        if ($canonicalUrl !== '') {
            $offer['url'] = $canonicalUrl;
        }

        $sellerName = $this->cleanText($sellerName);
        if ($sellerName !== '') {
            $offer['seller'] = [
                '@type' => 'Organization',
                'name' => $sellerName
            ];
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
            ]
        ];

        foreach ($candidates as $candidate) {
            $value = $this->value($data, $candidate['price'], null);
            if (is_numeric($value) && (float)$value > 0) {
                $currency = $this->value($data, $candidate['currency'], '');
                if ($currency === '') {
                    $currency = $this->value($data, 'prices.default.currency', '');
                }

                return [
                    'price' => (float)$value,
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

        // Do not guess. If plentyShop does not provide a salability flag,
        // omitting availability is safer than publishing a potentially false value.
        return null;
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
