<?php

namespace FeedbackGeoFM\Providers;

use FeedbackGeoFM\Contexts\FeedbackSingleItemContext;
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
use IO\Helper\TemplateContainer;
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

        // Official Ceres context extension point for the single item template.
        // Ceres maps tpl.item to SingleItemContext and exposes its context via
        // IO.ctx.item / IO.intl.ctx.item. The custom context extends the Ceres
        // implementation and only forwards the already loaded variation map
        // into item.documents[0].data for the layout-container provider.
        $singleItemContextListener = function (TemplateContainer $templateContainer, $templateData = []) {
            $templateContainer->setContext(FeedbackSingleItemContext::class);
            return false;
        };

        $dispatcher->listen('IO.ctx.item', $singleItemContextListener, 0);
        $dispatcher->listen('IO.intl.ctx.item', $singleItemContextListener, 0);

        $productSchemaEnabled = $coreHelper->configValueAsBool(
            FeedbackCoreHelper::KEY_SCHEMA_PRODUCT_OFFER_ENABLED,
            true
        );
        $disableCeresProduct = $coreHelper->configValueAsBool(
            FeedbackCoreHelper::KEY_SCHEMA_DISABLE_CERES_PRODUCT,
            true
        );

        if ($productSchemaEnabled && $disableCeresProduct) {
            // Ceres 5.0.81 resolves the metadata template via getPartial('page-metadata').
            // Therefore overriding the Twig view name alone is not sufficient. Replace the
            // IO partial mapping after Ceres' default mapping (Ceres uses priority 100;
            // custom overrides use priority 0 according to the PlentyONE theme docs).
            // Set all standard partials explicitly before stopping the chain so the page
            // remains fully defined even if no other template listener has run yet.
            $dispatcher->listen(
                'IO.init.templates',
                function (Partial $partial) {
                    $partial->set('head', 'Ceres::PageDesign.Partials.Head');
                    $partial->set('header', 'Ceres::PageDesign.Partials.Header.Header');
                    $partial->set('footer', 'Ceres::PageDesign.Partials.Footer');
                    $partial->set('page-design', 'Ceres::PageDesign.PageDesign');
                    $partial->set('page-metadata', 'FeedbackGeoFM::PageDesign.Partials.PageMetadata');

                    return false;
                },
                0
            );

            $dispatcher->listen(
                'IO.intl.init.templates',
                function (Partial $partial) {
                    $partial->set('head', 'Ceres::PageDesign.Partials.Head');
                    $partial->set('header', 'Ceres::PageDesign.Partials.Header.Header');
                    $partial->set('footer', 'Ceres::PageDesign.Partials.Footer');
                    $partial->set('page-design', 'Ceres::PageDesign.PageDesign');
                    $partial->set('page-metadata', 'FeedbackGeoFM::PageDesign.Partials.PageMetadata');

                    return false;
                },
                0
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
