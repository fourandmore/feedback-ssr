<?php

namespace FeedbackGeoFM\Widgets;

use Ceres\Widgets\Helper\BaseWidget;
use Ceres\Widgets\Helper\Factories\Settings\CustomClassSettingFactory;
use Ceres\Widgets\Helper\Factories\Settings\SpacingSettingFactory;
use Ceres\Widgets\Helper\Factories\WidgetDataFactory;
use Ceres\Widgets\Helper\WidgetCategories;
use Ceres\Widgets\Helper\WidgetTypes;
use Plenty\Modules\ShopBuilder\Factories\Settings\CheckboxSettingFactory;
use Plenty\Modules\ShopBuilder\Factories\WidgetSettingsFactory;

class FaqSchemaWidget extends BaseWidget
{
    protected $template = "FeedbackGeoFM::Widgets.FaqSchemaWidget";

    public function getData()
    {
        return WidgetDataFactory::make("FeedbackGeoFM::FaqSchemaWidget")
            ->withLabel("Widget.faqSchemaLabel")
            ->withPreviewImageUrl("/images/faq-schema.svg")
            ->withType(WidgetTypes::ITEM)
            ->withCategory(WidgetCategories::ITEM)
            ->withPosition(810)
            ->toArray();
    }

    public function getSettings()
    {
        /** @var WidgetSettingsFactory $settings */
        $settings = pluginApp(WidgetSettingsFactory::class);
        $settings->createSetting('customClass', CustomClassSettingFactory::class);
        $settings->createSetting('spacing', SpacingSettingFactory::class, [
            'usePadding' => true,
            'useMargin' => true
        ]);

        $settings->createSetting('eyebrow')
            ->withType('text')
            ->withDefaultValue('FAQ')
            ->withName('Widget.faqEyebrow')
            ->withTooltip('Widget.faqEyebrowTooltip');

        $settings->createSetting('headline')
            ->withType('text')
            ->withDefaultValue('Häufig gestellte Fragen')
            ->withName('Widget.faqHeadline')
            ->withTooltip('Widget.faqHeadlineTooltip');

        $settings->createSetting('anchorId')
            ->withType('text')
            ->withDefaultValue('faq-serverseitig')
            ->withName('Widget.faqAnchorId')
            ->withTooltip('Widget.faqAnchorIdTooltip');

        $settings->createSetting('renderVisibleFaq', CheckboxSettingFactory::class)
            ->withDefaultValue(true)
            ->withName('Widget.faqRenderVisible')
            ->withTooltip('Widget.faqRenderVisibleTooltip');

        $settings->createSetting('renderSchema', CheckboxSettingFactory::class)
            ->withDefaultValue(true)
            ->withName('Widget.faqRenderSchema')
            ->withTooltip('Widget.faqRenderSchemaTooltip');

        $result = $settings->toArray();

        // A repeatable question/answer group. Raw setting configuration is used
        // because the generic container factory API differs between Ceres LTS
        // patch versions, while the underlying ShopBuilder format is stable.
        $result['faqEntries'] = [
            'type' => 'vertical',
            'isList' => '[1,30]',
            'required' => false,
            'options' => [
                'name' => 'Widget.faqEntries',
                'tooltip' => 'Widget.faqEntriesTooltip'
            ],
            'children' => [
                'question' => [
                    'type' => 'text',
                    'required' => false,
                    'defaultValue' => '',
                    'options' => [
                        'name' => 'Widget.faqQuestion',
                        'tooltip' => 'Widget.faqQuestionTooltip'
                    ]
                ],
                'answer' => [
                    'type' => 'noteEditor',
                    'required' => false,
                    'defaultValue' => '',
                    'options' => [
                        'name' => 'Widget.faqAnswer',
                        'tooltip' => 'Widget.faqAnswerTooltip'
                    ]
                ]
            ]
        ];

        return $result;
    }

    protected function getTemplateData($widgetSettings, $isPreview)
    {
        $entries = $this->normalizeEntries($widgetSettings);

        if ($isPreview && count($entries) === 0) {
            $entries = [
                [
                    'question' => 'Was ist der Vorteil dieses FAQ-Widgets?',
                    'answer' => '<p>Das sichtbare FAQ und das FAQPage-Schema werden aus denselben Inhalten serverseitig ausgegeben.</p>'
                ],
                [
                    'question' => 'Ist das Schema im initialen HTML enthalten?',
                    'answer' => '<p>Ja. Das JSON-LD wird bereits mit dem Server-Response ausgeliefert.</p>'
                ]
            ];
        }

        $anchorId = $this->sanitizeAnchorId((string)$this->getMobileSetting(
            $widgetSettings,
            'anchorId',
            'faq-serverseitig'
        ));

        return [
            'options' => [
                'eyebrow' => trim((string)$this->getMobileSetting($widgetSettings, 'eyebrow', 'FAQ')),
                'headline' => trim((string)$this->getMobileSetting(
                    $widgetSettings,
                    'headline',
                    'Häufig gestellte Fragen'
                )),
                'anchorId' => $anchorId,
                'renderVisibleFaq' => $this->toBool(
                    $this->getMobileSetting($widgetSettings, 'renderVisibleFaq', true)
                ),
                'renderSchema' => !$isPreview && $this->toBool(
                    $this->getMobileSetting($widgetSettings, 'renderSchema', true)
                ),
                'entries' => $entries
            ]
        ];
    }

    private function normalizeEntries(array $settings)
    {
        $rawEntries = [];

        if (array_key_exists('faqEntries', $settings)) {
            $rawEntries = $settings['faqEntries'];
        }

        if (is_array($rawEntries) && array_key_exists('mobile', $rawEntries)) {
            $rawEntries = $rawEntries['mobile'];
        }

        if (!is_array($rawEntries)) {
            return [];
        }

        $entries = [];

        foreach ($rawEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $question = $this->readEntryValue($entry, 'question');
            $answer = $this->readEntryValue($entry, 'answer');

            if ($question === '' || trim(strip_tags($answer)) === '') {
                continue;
            }

            $entries[] = [
                'question' => $question,
                'answer' => $answer
            ];
        }

        return $entries;
    }

    private function readEntryValue(array $entry, $key)
    {
        if (!array_key_exists($key, $entry)) {
            return '';
        }

        $value = $entry[$key];

        if (is_array($value) && array_key_exists('mobile', $value)) {
            $value = $value['mobile'];
        }

        if (is_array($value)) {
            return '';
        }

        return trim((string)$value);
    }

    private function sanitizeAnchorId($value)
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
        $value = trim((string)$value, '-_');

        return $value !== '' ? $value : 'faq-serverseitig';
    }

    private function getMobileSetting(array $settings, $key, $default = null)
    {
        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];
        if (is_array($value) && array_key_exists('mobile', $value)) {
            return $value['mobile'];
        }

        return $value !== null ? $value : $default;
    }

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
}
