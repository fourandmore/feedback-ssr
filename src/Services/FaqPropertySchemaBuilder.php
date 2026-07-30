<?php

namespace Feedback\Services;

use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueRepositoryContract;
use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueTextRepositoryContract;
use Plenty\Modules\Item\VariationProperty\Models\VariationPropertyValue;
use Plenty\Modules\Item\VariationProperty\Models\VariationPropertyValueText;

/**
 * Extracts a visible FAQ HTML block from a plentyShop item property and builds
 * a Schema.org FAQPage object from the same questions and answers.
 */
class FaqPropertySchemaBuilder
{
    /** @var VariationPropertyValueRepositoryContract */
    private $variationPropertyValueRepository;

    /** @var VariationPropertyValueTextRepositoryContract */
    private $variationPropertyValueTextRepository;

    public function __construct(
        VariationPropertyValueRepositoryContract $variationPropertyValueRepository,
        VariationPropertyValueTextRepositoryContract $variationPropertyValueTextRepository
    ) {
        $this->variationPropertyValueRepository = $variationPropertyValueRepository;
        $this->variationPropertyValueTextRepository = $variationPropertyValueTextRepository;
    }
    /**
     * @param mixed $itemData
     * @param int $propertyId
     * @param string $language
     * @return array
     */
    public function build($itemData, $propertyId = 151, $language = 'de', $variationId = -1)
    {
        $data = $this->toArray($itemData);
        $propertyId = (int)$propertyId;

        $result = [
            'propertyId' => $propertyId,
            'html' => '',
            'entries' => [],
            'jsonLd' => null,
            'status' => 'not-found',
            'source' => '',
            'resolvedVariationId' => 0
        ];

        if ($propertyId <= 0) {
            return $result;
        }

        $html = $this->findPropertyHtml($data, $propertyId, (string)$language);
        if ($html !== '') {
            $result['source'] = 'item-document';
        }

        if ($html === '') {
            $variationIds = $this->resolveVariationIds($data, (int)$variationId);

            foreach ($variationIds as $resolvedVariationId) {
                $html = $this->findPropertyHtmlByVariationId(
                    $resolvedVariationId,
                    $propertyId,
                    (string)$language
                );

                if ($html !== '') {
                    $result['source'] = 'variation-repository';
                    $result['resolvedVariationId'] = (int)$resolvedVariationId;
                    break;
                }
            }
        }

        if ($html === '') {
            return $result;
        }

        $entries = $this->parseEntries($html);
        if (empty($entries)) {
            return $result;
        }

        $entities = [];
        foreach ($entries as $entry) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $entry['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $entry['answerText']
                ]
            ];
        }

        $result['html'] = $html;
        $result['entries'] = $entries;
        $result['status'] = 'found';
        $result['jsonLd'] = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities
        ];

        return $result;
    }

    /**
     * Resolve the current and main variation ids from the plentyShop item document.
     * The main variation is included because properties can be inherited from it.
     *
     * @param array $data
     * @param int $variationId
     * @return array
     */
    private function resolveVariationIds(array $data, $variationId = -1)
    {
        $candidates = [];

        if ((int)$variationId > 0) {
            $candidates[] = (int)$variationId;
        }

        if (isset($data['variation']) && is_array($data['variation'])) {
            if (isset($data['variation']['id'])) {
                $candidates[] = $data['variation']['id'];
            }
            if (isset($data['variation']['variationId'])) {
                $candidates[] = $data['variation']['variationId'];
            }
        }

        if (isset($data['variationId'])) {
            $candidates[] = $data['variationId'];
        }

        if (isset($data['item']) && is_array($data['item'])) {
            if (isset($data['item']['mainVariationId'])) {
                $candidates[] = $data['item']['mainVariationId'];
            }
        }

        $result = [];
        foreach ($candidates as $candidate) {
            if (!is_numeric($candidate) || (int)$candidate <= 0) {
                continue;
            }

            $candidate = (int)$candidate;
            if (!in_array($candidate, $result, true)) {
                $result[] = $candidate;
            }
        }

        return $result;
    }

    /**
     * Load a text property directly from the variation property repositories.
     * This is the fallback for ShopBuilder item documents that do not contain
     * the properties result field.
     *
     * @param int $variationId
     * @param int $propertyId
     * @param string $language
     * @return string
     */
    private function findPropertyHtmlByVariationId($variationId, $propertyId, $language)
    {
        $relations = [];

        try {
            $relations = $this->variationPropertyValueRepository->findByVariationId((int)$variationId);
        } catch (\Exception $exception) {
            return '';
        }

        $relations = $this->toArray($relations);
        if (empty($relations)) {
            return '';
        }

        foreach ($relations as $relation) {
            $relation = $this->toArray($relation);
            if (empty($relation) || $this->resolvePropertyId($relation) !== (int)$propertyId) {
                continue;
            }

            $candidates = [];
            foreach (['valueTexts', 'texts', 'names', 'value', 'rawValue'] as $key) {
                if (array_key_exists($key, $relation)) {
                    $this->collectTextCandidates(
                        $relation[$key],
                        strtolower(trim((string)$language)),
                        $candidates,
                        0,
                        false
                    );
                }
            }

            $best = $this->selectBestCandidate($candidates);
            if ($best !== '') {
                return $this->decodeHtml($best);
            }

            $relationId = isset($relation['id']) ? (int)$relation['id'] : 0;
            if ($relationId <= 0) {
                continue;
            }

            $normalizedLanguage = strtolower(trim((string)$language));
            $shortLanguage = substr($normalizedLanguage, 0, 2);
            $languageCandidates = array_unique([
                $normalizedLanguage,
                str_replace('-', '_', $normalizedLanguage),
                str_replace('_', '-', $normalizedLanguage),
                $shortLanguage,
                $shortLanguage . '_DE',
                $shortLanguage . '-DE',
                'de',
                'de_DE',
                'de-DE'
            ]);

            foreach ($languageCandidates as $languageCandidate) {
                if ($languageCandidate === '') {
                    continue;
                }

                try {
                    $textModel = $this->variationPropertyValueTextRepository->show(
                        $relationId,
                        $languageCandidate
                    );
                } catch (\Exception $exception) {
                    $textModel = null;
                }

                $textData = $this->toArray($textModel);
                if (empty($textData) || !isset($textData['value'])) {
                    continue;
                }

                $html = $this->decodeHtml((string)$textData['value']);
                if ($this->isFaqHtml($html)) {
                    return $html;
                }
            }
        }

        return '';
    }

    /**
     * @param string $html
     * @return bool
     */
    private function isFaqHtml($html)
    {
        $html = $this->decodeHtml($html);

        return $html !== ''
            && stripos($html, '<details') !== false
            && stripos($html, '<summary') !== false;
    }

    /**
     * @param array $data
     * @param int $propertyId
     * @param string $language
     * @return string
     */
    private function findPropertyHtml(array $data, $propertyId, $language)
    {
        // plentyShop result fields can nest properties differently depending on
        // the active Ceres/IO version and the widgets used on the item page.
        // Scan the complete item document first before using known paths.
        $recursiveMatch = $this->findPropertyHtmlRecursive($data, (int)$propertyId, (string)$language, 0);
        if ($recursiveMatch !== '') {
            return $recursiveMatch;
        }

        $propertyCollections = [];

        if (isset($data['properties'])) {
            $propertyCollections[] = $data['properties'];
        }

        if (isset($data['variationProperties'])) {
            $propertyCollections[] = $data['variationProperties'];
        }

        if (isset($data['item']) && is_array($data['item']) && isset($data['item']['properties'])) {
            $propertyCollections[] = $data['item']['properties'];
        }

        if (isset($data['variation']) && is_array($data['variation']) && isset($data['variation']['properties'])) {
            $propertyCollections[] = $data['variation']['properties'];
        }

        foreach ($propertyCollections as $collection) {
            $collection = $this->toArray($collection);
            if (empty($collection)) {
                continue;
            }

            foreach ($collection as $propertyEntry) {
                $propertyEntry = $this->toArray($propertyEntry);
                if (empty($propertyEntry)) {
                    continue;
                }

                if ($this->resolvePropertyId($propertyEntry) !== (int)$propertyId) {
                    continue;
                }

                $candidates = [];
                foreach (['texts', 'text', 'value', 'values', 'propertyValue'] as $key) {
                    if (array_key_exists($key, $propertyEntry)) {
                        $this->collectTextCandidates(
                            $propertyEntry[$key],
                            strtolower(trim((string)$language)),
                            $candidates,
                            0,
                            false
                        );
                    }
                }

                if (isset($propertyEntry['property']) && is_array($propertyEntry['property'])) {
                    foreach (['texts', 'text', 'value', 'values'] as $key) {
                        if (array_key_exists($key, $propertyEntry['property'])) {
                            $this->collectTextCandidates(
                                $propertyEntry['property'][$key],
                                strtolower(trim((string)$language)),
                                $candidates,
                                0,
                                false
                            );
                        }
                    }
                }

                $best = $this->selectBestCandidate($candidates);
                if ($best !== '') {
                    return $this->decodeHtml($best);
                }
            }
        }

        return '';
    }

    /**
     * Recursively find a property entry in an item document.
     *
     * @param mixed $node
     * @param int $propertyId
     * @param string $language
     * @param int $depth
     * @return string
     */
    private function findPropertyHtmlRecursive($node, $propertyId, $language, $depth)
    {
        if ((int)$depth > 9 || $node === null) {
            return '';
        }

        $node = $this->toArray($node);
        if (empty($node)) {
            return '';
        }

        if ($this->resolvePropertyId($node) === (int)$propertyId) {
            $candidates = [];
            foreach (['valueTexts', 'texts', 'text', 'value', 'values', 'propertyValue', 'rawValue', 'names'] as $key) {
                if (array_key_exists($key, $node)) {
                    $this->collectTextCandidates(
                        $node[$key],
                        strtolower(trim((string)$language)),
                        $candidates,
                        0,
                        false
                    );
                }
            }

            $best = $this->selectBestCandidate($candidates);
            if ($best !== '') {
                return $this->decodeHtml($best);
            }
        }

        foreach ($node as $child) {
            if (!is_array($child) && !is_object($child)) {
                continue;
            }

            $found = $this->findPropertyHtmlRecursive(
                $child,
                (int)$propertyId,
                (string)$language,
                (int)$depth + 1
            );

            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    /**
     * @param array $entry
     * @return int
     */
    private function resolvePropertyId(array $entry)
    {
        $candidates = [];

        foreach (['propertyId', 'id'] as $key) {
            if (isset($entry[$key])) {
                $candidates[] = $entry[$key];
            }
        }

        if (isset($entry['property'])) {
            $property = $this->toArray($entry['property']);

            foreach (['id', 'propertyId'] as $key) {
                if (isset($property[$key])) {
                    $candidates[] = $property[$key];
                }
            }

            if (isset($property['names'])) {
                $names = $this->toArray($property['names']);
                if (isset($names['propertyId'])) {
                    $candidates[] = $names['propertyId'];
                }

                foreach ($names as $nameEntry) {
                    $nameEntry = $this->toArray($nameEntry);
                    if (isset($nameEntry['propertyId'])) {
                        $candidates[] = $nameEntry['propertyId'];
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int)$candidate > 0) {
                return (int)$candidate;
            }
        }

        return 0;
    }

    /**
     * @param mixed $value
     * @param string $language
     * @param array $candidates
     * @param int $depth
     * @param bool $languageMatch
     * @return void
     */
    private function collectTextCandidates($value, $language, array &$candidates, $depth, $languageMatch)
    {
        if ((int)$depth > 6 || $value === null) {
            return;
        }

        if (is_string($value)) {
            $text = trim($value);
            if ($text !== '') {
                $score = $this->scoreCandidate($text, $languageMatch);
                $candidates[] = [
                    'text' => $text,
                    'score' => $score
                ];
            }
            return;
        }

        if (is_object($value)) {
            $value = $this->toArray($value);
        }

        if (!is_array($value)) {
            return;
        }

        $entryLanguage = '';
        foreach (['lang', 'language', 'languageCode'] as $languageKey) {
            if (isset($value[$languageKey]) && !is_array($value[$languageKey]) && !is_object($value[$languageKey])) {
                $entryLanguage = strtolower(trim((string)$value[$languageKey]));
                break;
            }
        }

        $matchesLanguage = $languageMatch || $this->languageMatches($entryLanguage, $language);

        $languageKeys = array_unique([
            $language,
            str_replace('-', '_', $language),
            substr($language, 0, 2)
        ]);

        foreach ($languageKeys as $languageKey) {
            if ($languageKey !== '' && array_key_exists($languageKey, $value)) {
                $this->collectTextCandidates(
                    $value[$languageKey],
                    $language,
                    $candidates,
                    (int)$depth + 1,
                    true
                );
            }
        }

        foreach (['value', 'text', 'valueText', 'description', 'content'] as $preferredKey) {
            if (array_key_exists($preferredKey, $value)) {
                $this->collectTextCandidates(
                    $value[$preferredKey],
                    $language,
                    $candidates,
                    (int)$depth + 1,
                    $matchesLanguage
                );
            }
        }

        foreach ($value as $key => $child) {
            if (in_array((string)$key, [
                'lang',
                'language',
                'languageCode',
                'value',
                'text',
                'valueText',
                'description',
                'content'
            ], true)) {
                continue;
            }

            $this->collectTextCandidates(
                $child,
                $language,
                $candidates,
                (int)$depth + 1,
                $matchesLanguage
            );
        }
    }

    /**
     * @param string $entryLanguage
     * @param string $requestedLanguage
     * @return bool
     */
    private function languageMatches($entryLanguage, $requestedLanguage)
    {
        if ($entryLanguage === '' || $requestedLanguage === '') {
            return false;
        }

        if ($entryLanguage === $requestedLanguage) {
            return true;
        }

        return substr($entryLanguage, 0, 2) === substr($requestedLanguage, 0, 2);
    }

    /**
     * @param string $text
     * @param bool $languageMatch
     * @return int
     */
    private function scoreCandidate($text, $languageMatch)
    {
        $decoded = $this->decodeHtml($text);
        $score = $languageMatch ? 25 : 0;

        if (stripos($decoded, '<details') !== false) {
            $score += 100;
        }
        if (stripos($decoded, '<summary') !== false) {
            $score += 80;
        }
        if (stripos($decoded, 'faq-item') !== false) {
            $score += 60;
        }
        if (stripos($decoded, 'faq-answer') !== false) {
            $score += 40;
        }
        if (stripos($decoded, '<section') !== false) {
            $score += 20;
        }

        return $score;
    }

    /**
     * @param array $candidates
     * @return string
     */
    private function selectBestCandidate(array $candidates)
    {
        $bestText = '';
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            if (!is_array($candidate) || !isset($candidate['text']) || !isset($candidate['score'])) {
                continue;
            }

            $score = (int)$candidate['score'];
            $text = trim((string)$candidate['text']);

            if ($text !== '' && $score > $bestScore) {
                $bestText = $text;
                $bestScore = $score;
            }
        }

        return $bestScore >= 100 ? $bestText : '';
    }

    /**
     * @param string $html
     * @return array
     */
    private function parseEntries($html)
    {
        $html = $this->decodeHtml($html);
        $entries = [];
        $seen = [];
        $detailsMatches = [];

        preg_match_all('/<details\b[^>]*>(.*?)<\/details>/isu', $html, $detailsMatches);

        if (empty($detailsMatches[1])) {
            return [];
        }

        foreach ($detailsMatches[1] as $detailsHtml) {
            $summaryMatches = [];
            $answerMatches = [];

            preg_match('/<summary\b[^>]*>(.*?)<\/summary>/isu', $detailsHtml, $summaryMatches);
            if (empty($summaryMatches[1])) {
                continue;
            }

            preg_match(
                '/<div\b[^>]*class\s*=\s*(["\'])[^"\']*\bfaq-answer\b[^"\']*\1[^>]*>(.*?)<\/div>/isu',
                $detailsHtml,
                $answerMatches
            );

            $answerHtml = isset($answerMatches[2]) ? trim((string)$answerMatches[2]) : '';
            if ($answerHtml === '') {
                $answerHtml = preg_replace('/^.*?<\/summary>/isu', '', $detailsHtml, 1);
            }

            $question = $this->cleanText($summaryMatches[1]);
            $answerText = $this->cleanText($answerHtml);

            if ($question === '' || $answerText === '' || isset($seen[$question])) {
                continue;
            }

            $seen[$question] = true;
            $entries[] = [
                'question' => $question,
                'answerHtml' => $answerHtml,
                'answerText' => $answerText
            ];
        }

        return $entries;
    }

    /**
     * @param string $html
     * @return string
     */
    private function decodeHtml($html)
    {
        $html = trim((string)$html);
        if ($html === '') {
            return '';
        }

        $decodePass = 0;
        while (strpos($html, '<') === false
            && (strpos($html, '&lt;') !== false || strpos($html, '&amp;lt;') !== false)
            && $decodePass < 3
        ) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decodePass++;
        }

        return $html;
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
     * @return array
     */
    private function toArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof VariationPropertyValue || $value instanceof VariationPropertyValueText) {
            $modelData = $value->toArray();
            return is_array($modelData) ? $modelData : [];
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
