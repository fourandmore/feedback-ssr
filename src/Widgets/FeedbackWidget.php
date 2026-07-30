<?php

namespace Feedback\Widgets;

use Ceres\Widgets\Helper\BaseWidget;
use Ceres\Widgets\Helper\Factories\Settings\AppearanceSettingFactory;
use Ceres\Widgets\Helper\Factories\Settings\CustomClassSettingFactory;
use Ceres\Widgets\Helper\Factories\Settings\SpacingSettingFactory;
use Ceres\Widgets\Helper\Factories\WidgetDataFactory;
use Ceres\Widgets\Helper\WidgetCategories;
use Ceres\Widgets\Helper\WidgetTypes;
use Feedback\Helpers\FeedbackCoreHelper;
use Plenty\Modules\ShopBuilder\Factories\Settings\CheckboxSettingFactory;
use Plenty\Modules\ShopBuilder\Factories\WidgetSettingsFactory;

class FeedbackWidget extends BaseWidget
{
    protected $template = "Feedback::Widgets.FeedbackWidget";

    public function getData()
    {
        return WidgetDataFactory::make("Feedback::FeedbackWidget")
            ->withLabel("Widget.feedbackGeoLabel")
            ->withPreviewImageUrl("/images/feedback.svg")
            ->withType(WidgetTypes::ITEM)
            ->withCategory(WidgetCategories::ITEM)
            ->withPosition(800)
            ->toArray();
    }

    public function getSettings()
    {
        /** @var WidgetSettingsFactory $settings */
        $settings = pluginApp(WidgetSettingsFactory::class);
        $settings->createSetting('customClass', CustomClassSettingFactory::class);
        $settings->createSetting('appearance', AppearanceSettingFactory::class, [
            'optional' => false
        ]);
        $settings->createSetting('spacing', SpacingSettingFactory::class, [
            'usePadding' => true,
            'useMargin' => true
        ]);

        $settings->createSetting('timestampVisibility', CheckboxSettingFactory::class)
            ->withDefaultValue(false)
            ->withName('Widget.timestampVisibility')
            ->withTooltip('Widget.timestampVisibilityTooltip');

        $settings->createSetting('feedbacksPerPage')
            ->withType('number')
            ->withDefaultValue(10)
            ->withName('Widget.feedbacksPerPage')
            ->withTooltip('Widget.feedbacksPerPageTooltip');

        $settings->createSetting('serverSideRendering', CheckboxSettingFactory::class)
            ->withDefaultValue(true)
            ->withName('Widget.serverSideRendering')
            ->withTooltip('Widget.serverSideRenderingTooltip');

        $settings->createSetting('productOfferSchema', CheckboxSettingFactory::class)
            ->withDefaultValue(true)
            ->withName('Widget.productOfferSchema')
            ->withTooltip('Widget.productOfferSchemaTooltip');

        $settings->createSetting('schemaSellerName')
            ->withType('text')
            ->withDefaultValue('Four & More GmbH')
            ->withName('Widget.schemaSellerName')
            ->withTooltip('Widget.schemaSellerNameTooltip');

        $settings->createSetting('faqPropertySchema', CheckboxSettingFactory::class)
            ->withDefaultValue(true)
            ->withName('Widget.faqPropertySchema')
            ->withTooltip('Widget.faqPropertySchemaTooltip');

        $settings->createSetting('faqPropertyId')
            ->withType('number')
            ->withDefaultValue(151)
            ->withName('Widget.faqPropertyId')
            ->withTooltip('Widget.faqPropertyIdTooltip');

        return $settings->toArray();
    }

    protected function getTemplateData($widgetSettings, $isPreview)
    {
        // Existing ShopBuilder contents may not yet contain the settings added
        // by this fork. Always use safe fallbacks so an old content does not fail.
        $feedbacksPerPage = (int)$this->getMobileSetting($widgetSettings, 'feedbacksPerPage', 10);
        $feedbacksPerPage = max(1, min(10, $feedbacksPerPage));

        $timestampVisibility = $this->toBool(
            $this->getMobileSetting($widgetSettings, 'timestampVisibility', false)
        );
        $serverSideRendering = $this->toBool(
            $this->getMobileSetting($widgetSettings, 'serverSideRendering', true)
        );
        $productOfferSchema = $this->toBool(
            $this->getMobileSetting($widgetSettings, 'productOfferSchema', true)
        );
        $schemaSellerName = (string)$this->getMobileSetting(
            $widgetSettings,
            'schemaSellerName',
            'Four & More GmbH'
        );
        $faqPropertySchema = $this->toBool(
            $this->getMobileSetting($widgetSettings, 'faqPropertySchema', true)
        );
        $faqPropertyId = (int)$this->getMobileSetting($widgetSettings, 'faqPropertyId', 151);
        $faqPropertyId = max(1, $faqPropertyId);

        return [
            "options" => [
                "feedbacksPerPage" => $feedbacksPerPage,
                "timestampVisibility" => $timestampVisibility,
                // SSR and schema calls must never run in the ShopBuilder editor.
                // The editor has no reliable item document and BaseWidget returns
                // an empty preview when the generated Twig throws an exception.
                "serverSideRendering" => !$isPreview && $serverSideRendering,
                "productOfferSchema" => !$isPreview && $productOfferSchema,
                "schemaSellerName" => trim($schemaSellerName),
                "faqPropertySchema" => !$isPreview && $faqPropertySchema,
                "faqPropertyId" => $faqPropertyId
            ]
        ];
    }

    /**
     * Read a responsive ShopBuilder setting while remaining compatible with
     * values stored by older versions of the widget.
     *
     * @param array $settings
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
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
}
