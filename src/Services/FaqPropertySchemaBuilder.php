<?php

namespace FeedbackGeoFM\Services;

use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Item\Item\Models\Item;
use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;
use Plenty\Modules\Item\Variation\Models\Variation;
use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueRepositoryContract;
use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueTextRepositoryContract;
use Plenty\Modules\Item\VariationProperty\Models\VariationPropertyValue;
use Plenty\Modules\Item\VariationProperty\Models\VariationPropertyValueText;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\SingleItem;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;

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

    /** @var VariationRepositoryContract */
    private $variationRepository;

    /** @var ItemRepositoryContract */
    private $itemRepository;

    /** @var ItemSearchService */
    private $itemSearchService;

    public function __construct(
        VariationPropertyValueRepositoryContract $variationPropertyValueRepository,
        VariationPropertyValueTextRepositoryContract $variationPropertyValueTextRepository,
        VariationRepositoryContract $variationRepository,
        ItemRepositoryContract $itemRepository,
        ItemSearchService $itemSearchService
    ) {
        $this->variationPropertyValueRepository = $variationPropertyValueRepository;
        $this->variationPropertyValueTextRepository = $variationPropertyValueTextRepository;
        $this->variationRepository = $variationRepository;
        $this->itemRepository = $itemRepository;
        $this->itemSearchService = $itemSearchService;
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

        // Product-wide FAQs are maintained exclusively on the item's main
        // variation. Do not read the current child variation or the current
        // item document: property inheritance may be disabled and a child can
        // contain a different property value.
        $mainVariationId = $this->resolveMainVariationId($data, (int)$variationId);
        if ($mainVariationId <= 0) {
            return $result;
        }

        $result['resolvedVariationId'] = $mainVariationId;
        // Use the same SingleItem search path as IO's ItemService. Unlike the
        // variation-property repositories, this returns the complete webshop
        // item document (documents[0].data) for the explicitly requested main
        // variation and therefore includes item properties/characteristics.
        $html = $this->findPropertyHtmlByMainVariationDocument(
            $mainVariationId,
            $propertyId,
            (string)$language
        );

        if ($html !== '') {
            $result['source'] = 'main-variation-item-document';
        }

        if ($html === '') {
            $html = $this->findPropertyHtmlByVariationId(
                $mainVariationId,
                $propertyId,
                (string)$language
            );

            if ($html !== '') {
                $result['source'] = 'main-variation-repository';
            }
        }

        // PlentyONE can expose older item characteristics in the ShopBuilder
        // item document even though VariationPropertyValueRepositoryContract
        // does not return them. Load the owning item with its properties as a
        // second main-item source; never fall back to the child document.
        if ($html === '') {
            $itemId = $this->resolveItemId($data, $mainVariationId);
            if ($itemId > 0) {
                $html = $this->findPropertyHtmlByItemId(
                    $itemId,
                    $propertyId,
                    (string)$language
                );

                if ($html !== '') {
                    $result['source'] = 'main-item-repository';
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
     * Resolve the item's main variation. The item document is preferred. If
     * the reduced ShopBuilder payload does not contain the main variation ID,
     * load the current variation model and use its documented mainVariationId.
     *
     * @param array $data
     * @param int $variationId
     * @return int
     */
    private function resolveMainVariationId(array $data, $variationId = -1)
    {
        if (isset($data['item'])
            && is_array($data['item'])
            && isset($data['item']['mainVariationId'])
            && is_numeric($data['item']['mainVariationId'])
            && (int)$data['item']['mainVariationId'] > 0) {
            return (int)$data['item']['mainVariationId'];
        }

        if (isset($data['variation'])
            && is_array($data['variation'])
            && isset($data['variation']['mainVariationId'])
            && is_numeric($data['variation']['mainVariationId'])
            && (int)$data['variation']['mainVariationId'] > 0) {
            return (int)$data['variation']['mainVariationId'];
        }

        if (isset($data['mainVariationId'])
            && is_numeric($data['mainVariationId'])
            && (int)$data['mainVariationId'] > 0) {
            return (int)$data['mainVariationId'];
        }

        $currentVariationId = (int)$variationId;
        if ($currentVariationId <= 0
            && isset($data['variation'])
            && is_array($data['variation'])) {
            if (isset($data['variation']['id']) && is_numeric($data['variation']['id'])) {
                $currentVariationId = (int)$data['variation']['id'];
            } elseif (isset($data['variation']['variationId'])
                && is_numeric($data['variation']['variationId'])) {
                $currentVariationId = (int)$data['variation']['variationId'];
            }
        }

        if ($currentVariationId <= 0
            && isset($data['variationId'])
            && is_numeric($data['variationId'])) {
            $currentVariationId = (int)$data['variationId'];
        }

        if ($currentVariationId <= 0) {
            return 0;
        }

        try {
            $variation = $this->variationRepository->findById($currentVariationId);
        } catch (\Exception $exception) {
            return 0;
        }

        $variationData = $this->toArray($variation);
        if (isset($variationData['mainVariationId'])
            && is_numeric($variationData['mainVariationId'])
            && (int)$variationData['mainVariationId'] > 0) {
            return (int)$variationData['mainVariationId'];
        }

        // PlentyONE documents mainVariationId as NULL on the main variation.
        // In that case the current model ID is the required source itself.
        $isMain = isset($variationData['isMain'])
            && in_array($variationData['isMain'], [true, 1, '1', 'true'], true);
        $hasNullMainVariationId = array_key_exists('mainVariationId', $variationData)
            && $variationData['mainVariationId'] === null;

        if (($isMain || $hasNullMainVariationId)
            && isset($variationData['id'])
            && is_numeric($variationData['id'])
            && (int)$variationData['id'] > 0) {
            return (int)$variationData['id'];
        }

        if (isset($data['variation']) && is_array($data['variation'])) {
            $dataSaysMain = isset($data['variation']['isMain'])
                && in_array($data['variation']['isMain'], [true, 1, '1', 'true'], true);
            if ($dataSaysMain) {
                return $currentVariationId;
            }
        }

        return 0;
    }

    /**
     * Load the complete plentyShop item document for the resolved main
     * variation. ItemSearchService returns the same document structure used by
     * IO/Ceres, but the variation ID is supplied explicitly and can therefore
     * never drift to the currently selected child variation.
     *
     * @param int $mainVariationId
     * @param int $propertyId
     * @param string $language
     * @return string
     */
    private function findPropertyHtmlByMainVariationDocument(
        $mainVariationId,
        $propertyId,
        $language
    ) {
        if ((int)$mainVariationId <= 0) {
            return '';
        }

        try {
            $results = $this->itemSearchService->getResults([
                SingleItem::getSearchFactory([
                    'variationId' => (int)$mainVariationId
                ])
            ]);
        } catch (\Exception $exception) {
            return '';
        }

        $results = $this->toArray($results);
        if (empty($results)) {
            return '';
        }

        // Current IO versions return one result per search factory. Keep the
        // direct check as a compatibility path for installations returning the
        // single result without a numeric wrapper.
        $html = $this->findPropertyHtml(
            $results,
            (int)$propertyId,
            (string)$language
        );
        if ($html !== '') {
            return $html;
        }

        foreach ($results as $result) {
            $result = $this->toArray($result);
            if (empty($result)) {
                continue;
            }

            $html = $this->findPropertyHtml(
                $result,
                (int)$propertyId,
                (string)$language
            );
            if ($html !== '') {
                return $html;
            }
        }

        return '';
    }

    /**
     * Resolve the owning item without using data from another variation.
     *
     * @param array $data
     * @param int $mainVariationId
     * @return int
     */
    private function resolveItemId(array $data, $mainVariationId)
    {
        if (isset($data['item'])
            && is_array($data['item'])
            && isset($data['item']['id'])
            && is_numeric($data['item']['id'])
            && (int)$data['item']['id'] > 0) {
            return (int)$data['item']['id'];
        }

        if (isset($data['itemId'])
            && is_numeric($data['itemId'])
            && (int)$data['itemId'] > 0) {
            return (int)$data['itemId'];
        }

        if (isset($data['variation'])
            && is_array($data['variation'])
            && isset($data['variation']['itemId'])
            && is_numeric($data['variation']['itemId'])
            && (int)$data['variation']['itemId'] > 0) {
            return (int)$data['variation']['itemId'];
        }

        if ((int)$mainVariationId <= 0) {
            return 0;
        }

        try {
            $mainVariation = $this->variationRepository->findById((int)$mainVariationId);
        } catch (\Exception $exception) {
            return 0;
        }

        $mainVariationData = $this->toArray($mainVariation);
        if (isset($mainVariationData['itemId'])
            && is_numeric($mainVariationData['itemId'])
            && (int)$mainVariationData['itemId'] > 0) {
            return (int)$mainVariationData['itemId'];
        }

        return 0;
    }

    /**
     * Load item-level properties of the item owning the main variation. This
     * supports PlentyONE item characteristics that are present in the webshop
     * document but absent from the variation-property repository.
     *
     * @param int $itemId
     * @param int $propertyId
     * @param string $language
     * @return string
     */
    private function findPropertyHtmlByItemId($itemId, $propertyId, $language)
    {
        try {
            $item = $this->itemRepository->show(
                (int)$itemId,
                ['id', 'mainVariationId'],
                (string)$language,
                ['properties']
            );
        } catch (\Exception $exception) {
            return '';
        }

        $itemData = $this->toArray($item);
        if (empty($itemData)) {
            return '';
        }

        return $this->findPropertyHtml($itemData, (int)$propertyId, (string)$language);
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

        $text = $this->decodeTextEntities((string)$value);
        $text = strip_tags($text);
        $text = $this->decodeTextEntities($text);
        $text = $this->repairUtf8Mojibake($text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string)$text);
    }

    /**
     * Decode HTML entities repeatedly, but with a strict pass limit. PlentyONE
     * can return an HTML property in an already escaped item document and
     * escape that value once more while serialising the document. A single
     * html_entity_decode() then leaves strings such as
     * "F&amp;Atilde;&amp;frac14;r" only half decoded.
     *
     * @param string $text
     * @return string
     */
    private function decodeTextEntities($text)
    {
        $text = (string)$text;
        $decodePass = 0;

        while ($decodePass < 4) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }

            $text = $decoded;
            $decodePass++;
        }

        return $text;
    }

    /**
     * Repair the common UTF-8-as-Latin-1 byte sequences that remain after
     * fully decoding a doubly escaped property value. Byte literals keep this
     * independent from the source file's own character encoding and avoid a
     * broad conversion that could damage already correct UTF-8 text.
     *
     * @param string $text
     * @return string
     */
    private function repairUtf8Mojibake($text)
    {
        return str_replace(
            [
                "\xC3\x83\xC2\xA4",
                "\xC3\x83\xC2\xB6",
                "\xC3\x83\xC2\xBC",
                "\xC3\x83\xC2\x84",
                "\xC3\x83\xC2\x96",
                "\xC3\x83\xC2\x9C",
                "\xC3\x83\xC2\x9F",
                "\xC3\x83\xE2\x80\x9E",
                "\xC3\x83\xE2\x80\x93",
                "\xC3\x83\xC5\x93",
                "\xC3\x83\xC5\xB8",
                "\xC3\x83&#132;",
                "\xC3\x83&#150;",
                "\xC3\x83&#156;",
                "\xC3\x83&#159;",
                "\xC3\x82\xC2\xB0",
                "\xC3\x82\xC2\xB2",
                "\xC3\x82\xC2\xB3",
                "\xC3\x82\xC2\xA0",
                "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9C",
                "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D",
                "\xC3\xA2\xE2\x82\xAC\xC5\xBE",
                "\xC3\xA2\xE2\x82\xAC\xC5\x93",
                "\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2",
                "\xC3\xA2\xE2\x82\xAC\xCB\x9C",
                "\xC3\xA2\xE2\x82\xAC\xC2\xA6"
            ],
            [
                "\xC3\xA4",
                "\xC3\xB6",
                "\xC3\xBC",
                "\xC3\x84",
                "\xC3\x96",
                "\xC3\x9C",
                "\xC3\x9F",
                "\xC3\x84",
                "\xC3\x96",
                "\xC3\x9C",
                "\xC3\x9F",
                "\xC3\x84",
                "\xC3\x96",
                "\xC3\x9C",
                "\xC3\x9F",
                "\xC2\xB0",
                "\xC2\xB2",
                "\xC2\xB3",
                " ",
                "\xE2\x80\x93",
                "\xE2\x80\x94",
                "\xE2\x80\x9E",
                "\xE2\x80\x9C",
                "\xE2\x80\x99",
                "\xE2\x80\x98",
                "\xE2\x80\xA6"
            ],
            (string)$text
        );
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

        if ($value instanceof Item
            || $value instanceof Variation
            || $value instanceof VariationPropertyValue
            || $value instanceof VariationPropertyValueText) {
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
