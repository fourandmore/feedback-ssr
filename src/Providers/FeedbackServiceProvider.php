<?php

namespace FeedbackGeoFM\Providers;

use FeedbackGeoFM\Extensions\FeedbackFacet;
use FeedbackGeoFM\Extensions\TwigServiceProvider;
use FeedbackGeoFM\Helpers\FeedbackCoreHelper;
use FeedbackGeoFM\Widgets\FeedbackAverageWidget;
use FeedbackGeoFM\Widgets\FeedbackOrderWidget;
use FeedbackGeoFM\Widgets\FeedbackWidget;
use FeedbackGeoFM\Widgets\FaqSchemaWidget;
use FeedbackGeoFM\Widgets\RatingFilterWidget;
use IO\Helper\ResourceContainer;
use IO\Services\ItemService;
use Plenty\Modules\ShopBuilder\Contracts\ContentWidgetRepositoryContract;
use Plenty\Modules\Webshop\Template\Providers\TemplateServiceProvider as WebshopTemplateServiceProvider;
use Plenty\Modules\Webshop\ItemSearch\Helpers\FacetExtensionContainer;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Templates\Twig;

class FeedbackServiceProvider extends WebshopTemplateServiceProvider
{
    /**
     * @param Dispatcher $dispatcher
     * @param FeedbackCoreHelper $coreHelper
     * @param Twig $twig
     */
    public function boot(Dispatcher $dispatcher, FeedbackCoreHelper $coreHelper, Twig $twig)
    {
        $showRatingFacet = $coreHelper->configValueAsBool(FeedbackCoreHelper::KEY_SHOW_RATING_FACET);
        $showRatingSorting = $coreHelper->configValueAsBool(FeedbackCoreHelper::KEY_SHOW_RATING_SORTING);

        if ($showRatingFacet) {
            //add feedback facet extension
            /** @var FacetExtensionContainer $facetExtensionContainer */
            $facetExtensionContainer = pluginApp(FacetExtensionContainer::class);
            $facetExtensionContainer->addFacetExtension(pluginApp(FeedbackFacet::class));
        }

        if ($showRatingSorting) {   // Sorting on CategoryPage
            //add feedback sorting
            $dispatcher->listen(
                'IO.initAdditionalSorting',
                function (ItemService $itemService) {
                    $itemService->addAdditionalItemSorting(
                        'item.feedbackDecimal_asc',
                        'FeedbackGeoFM::Feedback.customerReviewsAsc'
                    );
                    $itemService->addAdditionalItemSorting(
                        'item.feedbackDecimal_desc',
                        'FeedbackGeoFM::Feedback.customerReviewsDesc'
                    );
                }
            );
        }

        $twig->addExtension(TwigServiceProvider::class); // Enable use of FeedbackServiceProvider in twig code

        $productSchemaEnabled = $coreHelper->configValueAsBool(
            FeedbackCoreHelper::KEY_SCHEMA_PRODUCT_OFFER_ENABLED,
            true
        );
        $disableCeresProduct = $coreHelper->configValueAsBool(
            FeedbackCoreHelper::KEY_SCHEMA_DISABLE_CERES_PRODUCT,
            true
        );

        if ($productSchemaEnabled && $disableCeresProduct) {
            $this->overrideTemplate(
                'Ceres::PageDesign.Partials.PageMetadata',
                'FeedbackGeoFM::PageDesign.Partials.PageMetadata'
            );
        }

        $dispatcher->listen(
            'IO.Resources.Import',
            function (ResourceContainer $resourceContainer) {
                $resourceContainer->addScriptTemplate('FeedbackGeoFM::Content.Scripts');
                $resourceContainer->addStyleTemplate('FeedbackGeoFM::Content.Styles');
            }
        );

        // register shop builder widgets
        /** @var ContentWidgetRepositoryContract $widgetRepository */
        $widgetRepository = pluginApp(ContentWidgetRepositoryContract::class);
        $widgetRepository->registerWidget(FeedbackWidget::class);
        $widgetRepository->registerWidget(FaqSchemaWidget::class);
        $widgetRepository->registerWidget(FeedbackAverageWidget::class);
        $widgetRepository->registerWidget(FeedbackOrderWidget::class);
        $widgetRepository->registerWidget(RatingFilterWidget::class);
    }

    public function register()
    {
        $this->getApplication()->register(FeedbackRouteServiceProvider::class);
    }
}
