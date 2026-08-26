<?php

namespace FeedbackGeoFM\Services;

/**
 * Resolves per-product YouTube metadata from PlentyONE variation properties.
 *
 * The resolver deliberately has no PlentyONE dependencies so the same data
 * shape can be tested locally. It supports the nested `variationProperties`
 * structure used by plentyShop/ShopBuilder as well as common property result
 * variants.
 */
class VideoPropertyResolver
{
    /**
     * @param mixed $itemData
     * @param int $youtubePropertyId
     * @param int $uploadDatePropertyId
     * @return array|null
     */
    public function resolve($itemData, $youtubePropertyId = 110, $uploadDatePropertyId = 158)
    {
        $data = $this->toArray($itemData);
        $youtubePropertyId = (int)$youtubePropertyId;
        $uploadDatePropertyId = (int)$uploadDatePropertyId;

        if (empty($data) || $youtubePropertyId <= 0 || $uploadDatePropertyId <= 0) {
            return null;
        }

        $youtubeValue = $this->findPropertyValueRecursive($data, $youtubePropertyId, 0);
        $uploadDateValue = $this->findPropertyValueRecursive($data, $uploadDatePropertyId, 0);

        $youtubeId = $this->extractYoutubeId($youtubeValue);
        $uploadDate = $this->normalizeUploadDate($uploadDateValue);

        if ($youtubeId === '' || $uploadDate === '') {
            return null;
        }

        return [
            'schemaVideoObject' => true,
            'schemaVideoName' => '',
            'schemaVideoEmbedUrl' => 'https://www.youtube-nocookie.com/embed/' . $youtubeId,
            'schemaVideoThumbnailUrl' => 'https://i.ytimg.com/vi/' . $youtubeId . '/hqdefault.jpg',
            'schemaVideoUploadDate' => $uploadDate,
            'schemaVideoDescription' => '',
            'schemaVideoDuration' => ''
        ];
    }

    /**
     * @param mixed $node
     * @param int $propertyId
     * @param int $depth
     * @return string
     */
    private function findPropertyValueRecursive($node, $propertyId, $depth)
    {
        if ($depth > 12) {
            return '';
        }

        $node = $this->toArray($node);
        if (empty($node)) {
            return '';
        }

        if ($this->matchesPropertyId($node, (int)$propertyId)) {
            $value = $this->extractPropertyValue($node);
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($node as $child) {
            if (!is_array($child) && !is_object($child)) {
                continue;
            }

            $value = $this->findPropertyValueRecursive($child, (int)$propertyId, $depth + 1);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array $node
     * @param int $propertyId
     * @return bool
     */
    private function matchesPropertyId(array $node, $propertyId)
    {
        foreach (['propertyId', 'id'] as $key) {
            if (isset($node[$key]) && is_numeric($node[$key]) && (int)$node[$key] === (int)$propertyId) {
                return true;
            }
        }

        if (isset($node['property'])) {
            $property = $this->toArray($node['property']);
            foreach (['id', 'propertyId'] as $key) {
                if (isset($property[$key])
                    && is_numeric($property[$key])
                    && (int)$property[$key] === (int)$propertyId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $node
     * @return string
     */
    private function extractPropertyValue(array $node)
    {
        $paths = [
            ['values', 'value'],
            ['propertyValue', 'value'],
            ['value'],
            ['text'],
            ['valueText']
        ];

        foreach ($paths as $path) {
            $value = $node;
            $found = true;

            foreach ($path as $key) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } else {
                    $found = false;
                    break;
                }
            }

            if ($found && !is_array($value) && !is_object($value)) {
                $value = $this->cleanScalar($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        // Some PlentyONE result fields expose translated values as a list.
        foreach (['values', 'texts', 'valueTexts', 'propertyValue'] as $key) {
            if (!isset($node[$key])) {
                continue;
            }

            $value = $this->firstScalarRecursive($node[$key], 0);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     * @param int $depth
     * @return string
     */
    private function firstScalarRecursive($value, $depth)
    {
        if ($depth > 5) {
            return '';
        }

        if (!is_array($value) && !is_object($value)) {
            return $this->cleanScalar($value);
        }

        $value = $this->toArray($value);
        if (empty($value)) {
            return '';
        }

        // Prefer actual value/text fields over IDs, language codes or metadata.
        foreach (['value', 'text', 'valueText', 'name'] as $preferredKey) {
            if (array_key_exists($preferredKey, $value)) {
                $candidate = $this->firstScalarRecursive($value[$preferredKey], $depth + 1);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        foreach ($value as $key => $child) {
            if (in_array((string)$key, ['id', 'propertyId', 'variationId', 'lang', 'language', 'languageCode'], true)) {
                continue;
            }

            $candidate = $this->firstScalarRecursive($child, $depth + 1);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function cleanScalar($value)
    {
        if ($value === null || is_bool($value)) {
            return '';
        }

        $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(strip_tags($value));

        return $value;
    }

    /**
     * Accept a naked YouTube ID as stored in property 110, but tolerate a
     * complete watch/embed/short URL as well.
     *
     * @param string $value
     * @return string
     */
    private function extractYoutubeId($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $patterns = [
            '/youtube(?:-nocookie)?\.com\/embed\/([A-Za-z0-9_-]{6,})/i',
            '/youtube\.com\/watch\?[^#]*v=([A-Za-z0-9_-]{6,})/i',
            '/youtu\.be\/([A-Za-z0-9_-]{6,})/i'
        ];

        foreach ($patterns as $pattern) {
            $matches = [];
            if (preg_match($pattern, $value, $matches) === 1 && !empty($matches[1])) {
                return $matches[1];
            }
        }

        $value = preg_replace('/[?#].*$/', '', $value);
        $value = trim((string)$value, " \t\n\r\0\x0B/");

        return preg_match('/^[A-Za-z0-9_-]{6,}$/', $value) === 1 ? $value : '';
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeUploadDate($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        // Keep an already valid ISO date/date-time unchanged.
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ][0-9:.+-]+Z?)?$/', $value) === 1
            && strtotime($value) !== false) {
            return $value;
        }

        // Plenty properties are often maintained in German display format.
        $matches = [];
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches) === 1) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return '';
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
}
