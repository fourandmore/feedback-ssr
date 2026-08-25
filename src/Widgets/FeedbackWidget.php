<?php

namespace FeedbackGeoFM\Widgets;

use Ceres\Widgets\Helper\BaseWidget;
use Ceres\Widgets\Helper\Factories\Settings\AppearanceSettingFactory;
use Ceres\Widgets\Helper\Factories\Settings\CustomClassSettingFactory;
use Ceres\Widgets\Helper\Factories\Settings\SpacingSettingFactory;
use Ceres\Widgets\Helper\Factories\WidgetDataFactory;
use Ceres\Widgets\Helper\WidgetCategories;
use Ceres\Widgets\Helper\WidgetTypes;
use FeedbackGeoFM\Helpers\FeedbackCoreHelper;
use Plenty\Modules\ShopBuilder\Factories\Settings\CheckboxSettingFactory;
use Plenty\Modules\ShopBuilder\Factories\WidgetSettingsFactory;

class FeedbackWidget extends BaseWidget
{
    protected $template = "FeedbackGeoFM::Widgets.FeedbackWidget";

    public function getData()
    {
        return WidgetDataFactory::make("FeedbackGeoFM::FeedbackWidget")
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

        $settings->createSetting('schemaShippingPolicy', CheckboxSettingFactory::class)
            ->withDefaultValue(true)
            ->withName('Widget.schemaShippingPolicy')
            ->withTooltip('Widget.schemaShippingPolicyTooltip');

        $settings->createSetting('schemaShippingCountries')
            ->withType('text')
            ->withDefaultValue('DE')
            ->withName('Widget.schemaShippingCountries')
            ->withTooltip('Widget.schemaShippingCountriesTooltip');

        $settings->createSetting('schemaHandlingTimeMin')
            ->withType('number')
            ->withDefaultValue(0)
            ->withName('Widget.schemaHandlingTimeMin')
            ->withTooltip('Widget.schemaHandlingTimeMinTooltip');

        $settings->createSetting('schemaHandlingTimeMax')
            ->withType('number')
            ->withDefaultValue(1)
            ->withName('Widget.schemaHandlingTimeMax')
            ->withTooltip('Widget.schemaHandlingTimeMaxTooltip');

        $settings->createSetting('schemaTransitTimeMin')
            ->withType('number')
            ->withDefaultValue(1)
            ->withName('Widget.schemaTransitTimeMin')
            ->withTooltip('Widget.schemaTransitTimeMinTooltip');

        $settings->createSetting('schemaTransitTimeMax')
            ->withType('number')
            ->withDefaultValue(3)
            ->withName('Widget.schemaTransitTimeMax')
            ->withTooltip('Widget.schemaTransitTimeMaxTooltip');

        $settings->createSetting('schemaReturnPolicy', CheckboxSettingFactory::class)
            ->withDefaultValue(true)
            ->withName('Widget.schemaReturnPolicy')
            ->withTooltip('Widget.schemaReturnPolicyTooltip');

        $settings->createSetting('schemaReturnCountries')
            ->withType('text')
            ->withDefaultValue('DE')
            ->withName('Widget.schemaReturnCountries')
            ->withTooltip('Widget.schemaReturnCountriesTooltip');

        $settings->createSetting('schemaReturnDays')
            ->withType('number')
            ->withDefaultValue(14)
            ->withName('Widget.schemaReturnDays')
            ->withTooltip('Widget.schemaReturnDaysTooltip');

        $settings->createSetting('schemaReturnPolicyUrl')
            ->withType('text')
            ->withDefaultValue('https://www.mephisto-tools.com/widerrufsrecht/')
            ->withName('Widget.schemaReturnPolicyUrl')
            ->withTooltip('Widget.schemaReturnPolicyUrlTooltip');

        $settings->createSetting('schemaVideoObject', CheckboxSettingFactory::class)
            ->withDefaultValue(false)
            ->withName('Widget.schemaVideoObject')
            ->withTooltip('Widget.schemaVideoObjectTooltip');

        $settings->createSetting('schemaVideoName')
            ->withType('text')
            ->withDefaultValue('')
            ->withName('Widget.schemaVideoName')
            ->withTooltip('Widget.schemaVideoNameTooltip');

        $settings->createSetting('schemaVideoEmbedUrl')
            ->withType('text')
            ->withDefaultValue('')
            ->withName('Widget.schemaVideoEmbedUrl')
            ->withTooltip('Widget.schemaVideoEmbedUrlTooltip');

        $settings->createSetting('schemaVideoThumbnailUrl')
            ->withType('text')
            ->withDefaultValue('')
            ->withName('Widget.schemaVideoThumbnailUrl')
            ->withTooltip('Widget.schemaVideoThumbnailUrlTooltip');

        $settings->createSetting('schemaVideoUploadDate')
            ->withType('text')
            ->withDefaultValue('')
            ->withName('Widget.schemaVideoUploadDate')
            ->withTooltip('Widget.schemaVideoUploadDateTooltip');

        $settings->createSetting('schemaVideoDescription')
            ->withType('text')
            ->withDefaultValue('')
            ->withName('Widget.schemaVideoDescription')
            ->withTooltip('Widget.schemaVideoDescriptionTooltip');

        $settings->createSetting('schemaVideoDuration')
            ->withType('text')
            ->withDefaultValue('')
            ->withName('Widget.schemaVideoDuration')
            ->withTooltip('Widget.schemaVideoDurationTooltip');

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
        $schemaShippingPolicy = $this->toBool(
            $this->getMobileSetting($widgetSettings, 'schemaShippingPolicy', true)
        );
        $schemaShippingCountries = (string)$this->getMobileSetting(
            $widgetSettings,
            'schemaShippingCountries',
            'DE'
        );
        $schemaHandlingTimeMin = max(0, (int)$this->getMobileSetting(
            $widgetSettings,
            'schemaHandlingTimeMin',
            0
        ));
        $schemaHandlingTimeMax = max($schemaHandlingTimeMin, (int)$this->getMobileSetting(
            $widgetSettings,
            'schemaHandlingTimeMax',
            1
        ));
        $schemaTransitTimeMin = max(0, (int)$this->getMobileSetting(
            $widgetSettings,
            'schemaTransitTimeMin',
            1
        ));
        $schemaTransitTimeMax = max($schemaTransitTimeMin, (int)$this->getMobileSetting(
            $widgetSettings,
            'schemaTransitTimeMax',
            3
        ));
        $schemaReturnPolicy = $this->toBool(
            $this->getMobileSetting($widgetSettings, 'schemaReturnPolicy', true)
        );
        $schemaReturnCountries = (string)$this->getMobileSetting(
            $widgetSettings,
            'schemaReturnCountries',
            'DE'
        );
        $schemaReturnDays = max(1, (int)$this->getMobileSetting(
            $widgetSettings,
            'schemaReturnDays',
            14
        ));
        $schemaReturnPolicyUrl = (string)$this->getMobileSetting(
            $widgetSettings,
            'schemaReturnPolicyUrl',
            'https://www.mephisto-tools.com/widerrufsrecht/'
        );
        $schemaVideoObject = $this->toBool(
            $this->getMobileSetting($widgetSettings, 'schemaVideoObject', false)
        );
        $schemaVideoName = (string)$this->getMobileSetting($widgetSettings, 'schemaVideoName', '');
        $schemaVideoEmbedUrl = (string)$this->getMobileSetting($widgetSettings, 'schemaVideoEmbedUrl', '');
        $schemaVideoThumbnailUrl = (string)$this->getMobileSetting($widgetSettings, 'schemaVideoThumbnailUrl', '');
        $schemaVideoUploadDate = (string)$this->getMobileSetting($widgetSettings, 'schemaVideoUploadDate', '');
        $schemaVideoDescription = (string)$this->getMobileSetting($widgetSettings, 'schemaVideoDescription', '');
        $schemaVideoDuration = (string)$this->getMobileSetting($widgetSettings, 'schemaVideoDuration', '');
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
                "schemaShippingPolicy" => !$isPreview && $schemaShippingPolicy,
                "schemaShippingCountries" => trim($schemaShippingCountries),
                "schemaHandlingTimeMin" => $schemaHandlingTimeMin,
                "schemaHandlingTimeMax" => $schemaHandlingTimeMax,
                "schemaTransitTimeMin" => $schemaTransitTimeMin,
                "schemaTransitTimeMax" => $schemaTransitTimeMax,
                "schemaReturnPolicy" => !$isPreview && $schemaReturnPolicy,
                "schemaReturnCountries" => trim($schemaReturnCountries),
                "schemaReturnDays" => $schemaReturnDays,
                "schemaReturnPolicyUrl" => trim($schemaReturnPolicyUrl),
                "schemaVideoObject" => !$isPreview && $schemaVideoObject,
                "schemaVideoName" => trim($schemaVideoName),
                "schemaVideoEmbedUrl" => trim($schemaVideoEmbedUrl),
                "schemaVideoThumbnailUrl" => trim($schemaVideoThumbnailUrl),
                "schemaVideoUploadDate" => trim($schemaVideoUploadDate),
                "schemaVideoDescription" => trim($schemaVideoDescription),
                "schemaVideoDuration" => trim($schemaVideoDuration),
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
