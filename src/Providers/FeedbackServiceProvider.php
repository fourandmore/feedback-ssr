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
use IO\Extensions\Functions\Partial;
use IO\Helper\ResourceContainer;
use IO\Services\ItemService;
use Plenty\Modules\ShopBuilder\Contracts\ContentWidgetRepositoryContract;
use Plenty\Modules\Webshop\ItemSearch\Helpers\FacetExtensionContainer;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Templates\Twig;

class FeedbackServiceProvider extends ServiceProvider
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
            FeedbackCoreHelper::KEY_SCHEMA_PRODUCT_OFFER_ENABLED
        );
        $disableCeresProduct = $coreHelper->configValueAsBool(
            FeedbackCoreHelper::KEY_SCHEMA_DISABLE_CERES_PRODUCT
        );

        if ($productSchemaEnabled && $disableCeresProduct) {
            $metadataOverride = function (Partial $partial) {
                $partial->set(
                    'page-metadata',
                    'FeedbackGeoFM::PageDesign.Partials.PageMetadata'
                );
            };

            // Ceres registers its partial with priority 100. A lower priority
            // runs afterwards and replaces only the shared metadata partial.
            $dispatcher->listen('IO.init.templates', $metadataOverride, 0);
            $dispatcher->listen('IO.intl.init.templates', $metadataOverride, 0);
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
