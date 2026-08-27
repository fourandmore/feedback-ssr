## 5.0.57
- Diagnostic multi-search based on the official PlentyONE/IO interfaces only.
- Uses the already confirmed `SingleItemContext` variation IDs.
- Creates one `SingleItem` search preset per test ID and executes the 10 searches together through `ItemSearchService::getResults()`.
- Does not change `hasVariant`; diagnostic only to verify the documented batch path without the `VariationList` filter.

## 5.0.55

- Diagnostic: compares documented `ItemService::getVariation($variationId)` with `getVariations($variationIds)` for exactly one confirmed salable variation.
- No ProductGroup/hasVariant mapping changes.

## 5.0.54

- Corrected diagnostic-only release after the PlentyONE code check.
- Removes disallowed/new global PHP calls introduced in 5.0.52 (`forbidden diagnostic type helper`, `get_class`, `array_key_first`, `array_map`, `strlen`, `substr`).
- Diagnostics now use only global PHP functions already present in the successfully built 5.0.50 base.
- Still does not modify `hasVariant`; it only inspects the documented one-argument `ItemService::getVariations($variationIds)` method.

## 5.0.52

- Diagnostic-only release for ProductGroup variants.
- Uses only documented `SingleItemContext` data and one-argument `ItemService::getVariations($variationIds)`.
- Exposes the actual response shape in `#feedback-geofm-variant-diagnostics` (`application/json`).
- Deliberately does not alter `hasVariant` in this diagnostic release.

## 5.0.50
- ProductGroup variants now use the documented `Ceres\Contexts\SingleItemContext`, extended through the official `IO.ctx.item` / `IO.intl.ctx.item` events.
- The variation data already loaded by Ceres is forwarded into the item document passed to `SingleItem.BeforeAddToBasket`.
- Full variation documents are loaded only through the documented IO method `ItemService::getVariations(array $variationIds)`.
- Experimental variation discovery paths from 5.0.47–5.0.49 were removed.
- Diagnostic marker updated to `5.0.50`.

## 5.0.49
- ProductGroup variants: variation IDs are processed before optional attribute-name lookups. Attribute-name or individual variation-batch failures can no longer suppress `hasVariant` entirely.

## 5.0.48

- ProductGroup variants are now discovered through the same `VariationAttributeMap` search preset used by the Ceres `/io/variations/map` endpoint.
- The older `ItemService::getVariationAttributeMap()` path is kept only as a fallback.
- `afterKey` pagination is handled defensively so large product groups are not truncated after the first result page.
- `getVariationList()` is the final ID fallback for items without variation attributes.

## 5.0.47

- ProductGroup variants are now discovered through the Ceres-aligned `ItemService::getVariationAttributeMap()`, including selectable products that are not returned by the stricter stock-salable ID query.
- Variant details are loaded in batches of 20 so large ProductGroups are processed completely.
- Real variation attributes are emitted as Schema.org `size`, `color`, `material`, or `pattern`, and `variesBy` is derived automatically.
- Variants receive unambiguous names based on the product name and real attribute values.
- `isVariantOf` now includes the complete parent name, parent description, and `variesBy`.
- Item-level shipping profiles are reused for all child variants instead of being queried repeatedly.

## Version 5.0.46 – Fully populate ProductGroup variants

- ProductGroup pages now load active and salable variants server-side through `IO\Services\ItemService`.
- `hasVariant` now contains real `Product` objects including SKU, price, availability, Offer and `shippingDetails`.
- Existing shipping-profile fallback logic is applied to every loaded variant.

## Version 5.0.45 – Resolve shipping profiles server-side

- Loads shipping profiles linked to the item via `ItemShippingProfilesRepositoryContract::findByItemId($itemId)`.
- Exact profile shipping prices now work even when Ceres 5.0.81 omits the shipping-profile relation from the storefront item document.
- Repository failures remain non-fatal; Plenty default costs and existing fallbacks continue to work.

## Version 5.0.44 – Support Ceres ItemShippingProfiles

- Shipping-profile detection now reads `variation.itemShippingProfiles`, `itemShippingProfiles`, and `item.itemShippingProfiles`.
- This matches the PlentyONE/Ceres variation payload where item-linked shipping profiles are exposed as `itemShippingProfiles`.
- Configured profile prices such as `57=19.90` can therefore override a generic `variation.defaultShippingCosts` parcel value such as 6.90.

## Version 5.0.43 – Profile price before Plenty default costs

- An explicitly configured shipping-profile price now takes precedence over `variation.defaultShippingCosts`.
- This corrects products where PlentyONE exposes a generic parcel value in the item document although the assigned shipping profile has a different fixed price.
- If no profile price matches, `defaultShippingCosts` remains the next source, followed by the parcel/freight fallback.

## Version 5.0.42 – Shipping fallback per shipping profile

- Adds exact `shipping profile ID = shipping price` mappings, e.g. `6=6.90; 9=59.00; 12=89.00`.
- PlentyONE calculated default shipping costs always keep priority.
- When PlentyONE costs are absent, an exact profile price takes precedence over the existing parcel/freight fallback.
- If no exact price exists for the assigned profile, the existing parcel/freight logic including the weight threshold remains active.
- Separate mappings with semicolons or line breaks; this also keeps decimal commas unambiguous.
- Shipping-profile detection now also supports PlentyONE's documented top-level `shippingProfiles` array and its `profileId`.

## Version 5.0.41 – GEO additions on the verified 5.0.40 baseline

- Preserves the working Ceres 5.0.81 `page-metadata` override from version 5.0.40.
- Adds optional per-plugin-set parcel and freight prices when PlentyONE omits calculated default shipping costs. PlentyONE data still takes precedence.
- Freight selection supports configured shipping profile IDs or a gross-weight threshold.
- YouTube and upload-date settings accept multiple comma-separated property IDs.
- `ProductGroup.hasVariant` is emitted only from real sibling variation documents present in the layout-container payload.
- FAQ, category rating stars, review SSR, and ProvenExpert output are unchanged.

## Version 5.0.40 – Ceres 5.0.81 override

- Replaces the actual `page-metadata` partial key through `IO.init.templates` and `IO.intl.init.templates`.
- Suppresses the Ceres Product/ProductGroup block server-side while preserving other metadata.
- Keeps the legacy client-side Feedback Product block disabled.

## Version 5.0.39 – Targeted Ceres Product schema suppression

- Ceres Product schema suppression no longer relies on page-type detection.
- The `schemaOrg` block passed to `PageMetadata.twig` is inspected directly.
- Only Ceres `Product`/`ProductGroup` JSON-LD is suppressed; `WebSite` and other metadata remain intact.
- External JSON-LD such as ProvenExpert is not modified.

## 5.0.36

- VideoObject is now generated server-side from configurable properties; defaults: 110 = YouTube ID and 158 = upload date.
- VideoObject is integrated as `Product.subjectOf` into the existing `feedback-product-offer-jsonld` block.
- Ceres Product suppression is more robust on item pages and plugin-set previews and no longer depends on comparing `isItem()` with the string `"1"`.
- New configuration for automatic video output and both property IDs.

## 5.0.35

- The Ceres metadata partial is now replaced via the official PlentyONE `overrideTemplate()` method.
- Newly introduced configuration values that have not yet been saved reliably use their intended `true` default.
- An explicitly disabled switch continues to be respected.
- Product suppression therefore also works in plugin-set previews without changing FAQ, reviews, category ratings, or other metadata.

## 5.0.34

- Added a default-on configuration to suppress the parallel Ceres Product schema server-side on item pages.
- Suppression is enabled only while the FeedbackGeoFM Product/ProductGroup/Offer schema is enabled.
- FAQPage, BreadcrumbList, reviews, category ratings, metadata, and the WebSite schema on other page types remain intact.

## 5.0.33

- Product, ProductGroup, Offer, and Review text is emitted as transport-safe JSON Unicode escapes and decodes back to the original Unicode text.
- The legacy client-side review Product block is suppressed when the server-rendered `feedback-product-offer-jsonld` block exists.
- The FAQ property ID is configurable per plugin set; `151` remains the default and invalid-value fallback.
- Recommended settings for Four More, Billiard Royal, and Mephisto are documented.
- The required category assignment `Feedback category ratings` → `Ceres::CategoryItem.BeforePrices` is explicitly documented.

## 5.0.32

- The configured manufacturer is now authoritative per plugin set and overrides ambiguous PlentyONE manufacturer fields.
- The EU responsible person in `responsibleName` is no longer emitted as `Product.manufacturer`.
- With an empty configuration, automatic resolution uses `legalName`, `externalName`, or `name`, but never `responsibleName`.
- Brand, manufacturer, and Offer seller remain separate Schema.org roles; FAQ and all other Product/Offer features are unchanged.

## 5.0.31

- Server-rendered FAQPage JSON-LD now emits non-ASCII characters as standards-compliant JSON Unicode escapes.
- Umlauts, `ß`, degree signs and typographic punctuation therefore remain intact even if the Plenty/Twig output path alters literal UTF-8.
- `JSON.parse()` and search engines still receive the complete original Unicode text.
- Product/ProductGroup/Offer output remains unchanged.

## 5.0.30

- Doubly HTML-encoded FAQ text from property 151 is now fully decoded before schema generation.
- Common UTF-8/Latin-1 mojibake affecting umlauts, `ß`, special characters and typographic punctuation is repaired selectively.
- Already valid UTF-8 remains unchanged; Product/ProductGroup/Offer output is unchanged.

## 5.0.29

- Property 151 is loaded from the complete main-variation item document through `ItemSearchService` and the `SingleItem` preset.
- This follows the official IO `ItemService` path and does not use the deprecated `ItemDataLayer` interface.
- Child-variation documents remain excluded; the existing main-variation repositories are retained as fallbacks only.
- The diagnostic source for the new path is `main-variation-item-document`.
- Product, ProductGroup, and Offer output remains unchanged.

## 5.0.28

- Added a server-side FAQ fallback through the owning main item with its `properties` relation loaded.
- Supports item properties/characteristics that are present in the ShopBuilder item document but absent from the variation-property repository.
- Main variation resolution and exclusion of child-variation values remain enforced.
- The diagnostic source for this path is `main-item-repository`.
- Product, ProductGroup, and Offer output remains unchanged.

## 5.0.27

- FAQPage JSON-LD now reads property 151 exclusively from the item's main variation on every product page.
- Disabled property inheritance on child variations no longer prevents FAQ schema output.
- If the layout container omits the main variation ID, it is resolved server-side through the variation repository.
- Values from the current child variation and the current ShopBuilder item document are intentionally ignored for property 151.
- Product, ProductGroup, and Offer output remains unchanged.

## 5.0.26

- FAQPage JSON-LD is now rendered on purchasable child variations when property 151 is inherited from the main variation.
- FAQ resolution now considers `propertyVariationId`, `mainVariationId`, and `parentVariationId` in direct and nested plentyShop item data.
- Product/ProductGroup/Offer output remains unchanged: non-purchasable main variations receive `ProductGroup`, while purchasable variations receive `Product` with `Offer` and `isVariantOf`.

## 5.0.25

- Replaced the deprecated `Ceres::SingleItem.BeforePrice` default assignment for Product and FAQ data providers with `Ceres::SingleItem.BeforeAddToBasket`.
- Container arguments are now resolved from direct item data, `documents[0].data`, `item.documents[0].data`, and object arguments.

## 5.0.24

- Moved Product/ProductGroup/Offer JSON-LD to a real server-side layout-container data provider; the ShopBuilder widget no longer emits Product markup through `<script2>`.
- Non-purchasable parent variations with child variations are represented as `ProductGroup` without an `Offer`.
- Concrete variants receive a `Product`, variation-specific `productID` and Offer IDs, plus an item-stable `isVariantOf`/`productGroupID` link.
- Separated brand, manufacturer and seller roles; `manufacturer.responsibleName` is preferred.
- Added global plugin settings for Product/Offer, variant dimensions, shipping and returns. Shipping details default to disabled until the data has been verified.

## 5.0.22

- FAQPage for property 151 is rendered by a server-side layout-container data provider as populated JSON-LD.

- Removed the empty FAQ `<script>` from the ShopBuilder widget; diagnostic marker remains.

## 5.0.21

- Changed the technical plugin name from `Feedback` to `FeedbackGeoFM`.
- Changed the PHP namespace from `Feedback\...` to `FeedbackGeoFM\...` throughout the plugin.
- Updated Twig template, translation, `plugin_path()`, SSR entry and data provider references to `FeedbackGeoFM`.
- Updated the plugin configuration prefix to `FeedbackGeoFM`.
- Intentionally kept the plentyONE core namespaces `Plenty\Modules\Feedback\...` unchanged.

# 5.0.20

- Placeholder authors such as “Unknown”, “Unbekannt” and “Guest” are rendered as “Anonymous buyer” or “Anonymer Käufer” in visible SSR reviews and Review JSON-LD.
- `Product.category` is derived from the PlentyONE default category or the canonical category path.
- `Offer.itemCondition` is derived from the PlentyONE item condition.
- Product-specific `OfferShippingDetails` are generated from `variation.defaultShippingCosts` and configurable destinations and delivery times.
- A standard `MerchantReturnPolicy` is attached to the Offer seller `Organization`.
- An optional `VideoObject` can be attached through `Product.subjectOf` when complete video data is configured.
- Added ShopBuilder settings for shipping, returns and video markup.

# 5.0.19

- FAQPage JSON-LD from the item property and the manual FAQ widget is emitted as a real `<script type="application/ld+json">` element in the server HTML.
- Removed the previous non-standard FAQ output via `<script2>`.
- Product, Offer, Review and rating logic remain unchanged.

# 5.0.18

- FAQ property 151: added recursive item document scan, model normalization, URL variation fallback and diagnostic meta tag.

- Updated project, developer and maintainer metadata to **Four & More GmbH**.
- Updated contact information in plugin metadata.
- Removed obsolete CODEOWNERS and repository references.
- Updated README and package metadata to **Feedback Product Offer GEO F&M**.
- Preserved original copyright and license notices and added the Four & More GmbH modification notice.

# 5.0.15

- Renamed the visible plugin and main widget to **“Feedback Product Offer GEO F&M”**.
- FAQPage JSON-LD can now be rendered server-side from an item or variation property.
- The default property containing existing FAQ HTML is **ID 151**.
- Property FAQ schema can be configured independently of review SSR and Product/Offer schema.
- Added ShopBuilder settings for activation and property ID.

# 5.0.14

- Added the ShopBuilder widget “Server-rendered FAQ + schema”.
- Visible FAQ content and FAQPage JSON-LD are generated from the same questions and answers.
- FAQPage is included in the initial server HTML.
- Compatibility cleanup for the legacy `automatic-faq-schema` block.

## 5.0.13

- Items without reviews now immediately display “Item reviews (0)”.
- A clear empty state is rendered server-side and in the interactive view.
- Empty rating stars and distribution bars are hidden when the review count is zero; the review form remains available.

## 5.0.12

- `Product` and `Offer` are now output independently of visible server-side review rendering.
- Items without reviews still receive complete product schema when Product/Offer output is enabled.
- `AggregateRating` and individual `Review` objects are still added when reviews exist.
- ShopBuilder tooltips now describe the independent settings.

## 5.0.11

- Hydration hotfix: Keep server-rendered reviews visible until the Vue component has fully loaded real review data and emits an explicit ready event.
- Prevents the empty duplicate “Customer reviews ()” block.

## 5.0.10

- Removes the empty second review section “Customer reviews ()” when SSR is enabled.
- The server-rendered review block remains visible until the interactive Vue component has loaded real data.
- Filters, review form and load-more functionality remain available.

## v5.0.9 (2026-07-30)

### Changed

- Changed the visible plugin name to “Kunden Feedback Four & More”.
- JSON-LD is emitted via `<script2>` in the ShopBuilder widget so the schema is no longer displayed as visible text.
- Stock and availability logic remain unchanged.

## v5.0.8 (2026-07-30)

- PlentyONE compiler compatibility: replaced direct object creation with dependency injection.
- Removed the disallowed `is_scalar()` function.
- Removed dynamic object access from the product schema builder; all traversal now uses arrays.

# Release Notes for Feedback


## v5.0.6 (2026-07-30)

### Added

- The first review page can be rendered into the initial server response.
- The item review widget can output a complete server-side `Product`/`Offer` schema.
- Existing aggregate ratings and individual reviews are integrated into the same Product schema.
- Seller name and schema output can be configured in ShopBuilder.

### TODO

- After updating, regenerate ShopBuilder contents and test the plugin in a separate test plugin set.

## v5.0.4 (2026-02-03) <a href="https://github.com/plentymarkets/feedback-plugin/compare/5.0.3...5.0.4" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### Fixed
- Modified version of `eslint` and other packages in package.json.
- Fixed a warning for number of feedbacks type mismatch.

## v5.0.3 (2026-01-06) <a href="https://github.com/plentymarkets/feedback-plugin/compare/5.0.2...5.0.3" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### Fixed
- A missing CSS class has been added again.

## v5.0.2 (2025-11-17) <a href="https://github.com/plentymarkets/feedback-plugin/compare/5.0.1...5.0.2" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### Added
- Added error and success messages when saving a review. Thanks to @MaxBentz for the contribution.
- Clicking an item of the review widget on the order confirmation page now opens the feedback form. Thanks to @MaxBentz for the contribution.

### Fixed
- The accessibility of the review form has been improved.

## v5.0.1 (2025-04-17) <a href="https://github.com/plentymarkets/feedback-plugin/compare/5.0.0...5.0.1" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### Fixed

- Fixed an error related to Webpack.

## v5.0.0 (2025-04-02) <a href="https://github.com/plentymarkets/feedback-plugin/compare/4.0.9...5.0.0" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### TODO

- Due to the extent of modifications, we recommend testing the new version within a separate plugin set to ensure compatibility and prevent any potential conflicts with existing plugins or themes.

### Added

- Accessibility was improved.
- Widget setting to hide the feedback widget on the order confirmation page if the shipping date has not been set yet. We would like to thank user @MaxBentz for their contribution.

### Changed

- Made feedback plugin to get its data through the feedback microservice instead of its PHP logic.
- Changed feedback counts data to be delivered by the feedback microservice instead of being fetched from the PHP logic.
- The rating on the category pages is now loaded via the item list (itemList).
- Removed `Allow reviews without rating` option from plugin configuration.
- Change translation for a configuration label.

## v4.0.9 (2023-06-19) <a href="https://github.com/plentymarkets/feedback-plugin/compare/4.0.8...4.0.9" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### Changed

- It's now possible to display the average rating on category level. We would like to thank user @MaxBentz for their contribution.
- It's now possible to display attributes on the order confirmation page. We would like to thank user @MaxBentz for their contribution.
- CSS adjustments. We would like to thank user @MaxBentz for their contribution.

## v4.0.8 (2023-01-05) <a href="https://github.com/plentymarkets/feedback-plugin/compare/4.0.7...4.0.8" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### Changed

- The logic for anonymisation was moved to the core.
- Visibility settings were moved from the widget to the plugin configuration.

## v4.0.7 (2022-10-21) <a href="https://github.com/plentymarkets/feedback-plugin/compare/4.0.6...4.0.7" target="_blank" rel="noopener"><b>Overview of all changes</b></a>

### Changed
- 
- Clicking the image of an item in the rating widget for the order confirmation now leads to the item page. We would like to thank user @MaxBentz for their contribution.

### Fixed
 
- The order of the rating stars was incorrect when editing a feedback. This has been fixed. We would like to thank user @MaxBentz for their contribution.

## v4.0.6 (2021-10-20)

### Changed

- The feedback plugin was adapted to the rebranding to **plentyShop LTS".

### Fixed

- When selecting a rating filter, it was not displayed as selected.
- In the structured data, an explicit type was stored for the author.

## v4.0.5 (2021-08-17)

### TODO

- After updating the feedback plugin to v4.0.5, it is necessary to regenerate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Behoben

- Due to an error in the name resolution for author fields, the author name was sometimes not included in the structured data. This has been fixed.

## v4.0.4 (2021-07-06)

### TODO

- After updating the feedback plugin to v4.0.4, it is necessary to regenerate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Behoben

- The use of multiple feedback widgets on the single item view led to errors. This has been fixed.
- While editing a customer review, a markup error was visible. This has been fixed.

## v4.0.3 (2021-05-10)

### TODO

- After updating the feedback plugin to v4.0.3, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Behoben

- Feedbacks submitted without an author's name led to an error. This has been fixed.

## v4.0.2 (2021-05-05)

### TODO

- After updating the feedback plugin to v4.0.2, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Fixed

- Line breaks in comments for customer reviews were rendered as `<br>` tags. This has been fixed.

## v4.0.1 (2021-04-28)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0.27 or higher.
- After updating the feedback plugin to v4.0.1, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Fixed

- The number of feedbacks in the star rating widget was incorrectly positioned. As a result, the rating stars concealed the feedback count. This has been fixed.

## v4.0.0 (2021-04-14)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0.27 or higher.
- After updating the feedback plugin to v4.0.0, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Added

- The feedback plugin is now compatible with Vue Server-Side Rendering.
- We added a Webpack 5 build process.
- The logic and data auf the Vue components have been moved to a separate VueX store.

### Changed

- Widgets are no longer defined and registered via the obsolete `contentwidgets.json` file but via PHP classes.
- All Vue components have been realised as precombiled Single File Component files that are loaded asynchronously. 

## v3.6.4 (2021-04-13)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.6.4, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Behoben

- We fixed a security issue through which cross-site scripting was made possible.
- Under certain circumstanes, the field for the author's name was not displayed on the order confirmation of guest orders. This has been fixed.

## v3.6.3 (2021-03-26)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.6.3, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Fixed

- Feedbacks could not be verified, when written from a guests order confirmation. This has been fixed.
- Incorrectly cached data lead to wrongly linked feedbacks on the order confirmation.

## v3.6.2 (2021-03-03)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.6.2, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Changed

- The feedback widget for the order confirmation now displays a notification on contents in the ShopBuilder on which it cannot be used.

### Fixed

- Under certain circumstances, the author's name could be left empty in customer reviews. This has been fixed. 

## v3.6.1 (2020-12-15)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.6.1, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Fixed

- A missing condition for the output of the structured data led to errors for unrated articles. 
- The title of the feedback facet was not available in the CMS » Multilingualism menu.


## v3.6.0 (2020-12-08)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.6.0, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Added

- The field "reviews" has been added to the structured data.

### Changed

- The structured data is now directly generated into the head of the HTML document.

## v3.5.3 (2020-10-14)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.5.3, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Fixed

- The translation key "customerReviews" was mistakenly removed. This has been fixed.


## v3.5.2 (2020-10-13)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.5.2, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Changed

- The buttons for editing and deleting customer ratings submitted by guest accounts have been removed from the feedback widget.

### Fixed

- Certain combinations of settings could lead to verified purchases of items not being registered. This has been fixed.
- Sorting items on category pages according to customer rating now works as intended.

## v3.5.1 (2020-08-19)

### TODO

- This version of the feedback plugin is only compatible with Ceres v5.0 or higher.
- After updating the feedback plugin to v3.5.1, it is necessary to re-generate ShopBuilder widgets via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Changed

- When a user clicks the number next to the star rating, the page now scrolls to the next superordinate element that is visible. 

### Fixed

- Under certain circumstances, customers were unable to submit a rating on the order confirmation page.
- Due to an error, facet filters for ratings were no longer rendered. 
- Certain combinations of settings could lead to an erroneous display of the "Verified purchase" badge.

## v3.5.0 (2020-04-14)

### TODO

- This version of the feedback plugin is only compatible with Ceres version 5.0 or higher.
- After updating the feedback plugin to version 3.5.0, it is necessary to re-generate the ShopBuilder contents via the **Regenerate contents** button in the **CMS » ShopBuilder** menu.

### Added

- A new REST route has been added in order to load the required data for the feedback widget as needed.

### Changed

- The feedback plugin is now compatible with Ceres version 5.0.
- The feedback plugin can now be used for item set contents.

## v3.4.1 (2020-03-04)

### TODO

- This version of the feedback plugin is only compatible with Ceres version 4.6 or higher.

### Changed

- The structured data is now output via '<script2' tags.

### Fixed

- Due to an error, the page did not scroll to the feedback plugin after the user clicked on the number of submitted ratings. This has been fixed.

## v3.4.0 (2020-01-20)

### TODO

- After updating the plugin to v3.4.0, you need to refresh ShopBuilder contents by clicking the button **Regenerate contents** in the **CMS » ShopBuilder** menu.

### Added

- The star rating widget is now able to display the number of submitted ratings. Clicking the number next to the star ratings redirects the user to the item review widget if this widget is also placed in the same content.

### Changed

- The star rating widget now initially loads necessary data. This improves the performance slightly.  

### Fixed

- Item bundles were displayed incorrectly in the item review widget for the order confirmation page. This has been fixed.
- Under certain circumstances, faulty tooltips were displayed in Ceres. This has been fixed.
- The item review widget for the order confirmation page was not displayed in ShopBuilder contents that were anything but an order confirmation page. This has been fixed.

## v3.3.0 (2019-12-18)

### Added

- We added a Shopbuilder widget for filtering items on the category page by rating.

## v3.2.2 (2019-10-22)

### TODO

- After updating to v3.2.2, you need to refresh the widgets by clicking **Regenerate contents** in the **CMS » ShopBuilder** menu.

### Changed

- The feedback widget for the order confirmation page has been converted from a grid layout to a column layout in order to improve compatibility with older browsers.

### Fixed 

- In the feedback widget for the order confirmation page, certain images were not displayed correctly. This has been fixed.
- The item's name is now displayed instead of the variation name.

## v3.2.1 (2019-10-09)

### Added

- A note was added on the order confirmation page informing the user that the widget is loading.

### Fixed

- Due to an error, no data for the feedback widget was loaded on the order confirmation page. This has been fixed.
- Code changes in the `FeedbackService` resulted in an improved stability of the plugin.

## v3.2.0 (2019-09-30)

### TODO

- After updating to v3.2.0, you need to refresh the widgets by clicking **Regenerate contents** in the **CMS » ShopBuilder** menu.

### Added

- The feedback widget can now be used on the order confirmation page.

## v.3.1.2 (2019-09-02)

### Fixed 

- Due to an error, structured data was invalid if no star rating had been given for an item. This has been fixed.
- Due to an error, the setting "Show empty star rating" was not working as intended. This has been fixed. 

## v3.1.1 (2019-08-22)

### Fixed

- Due to an error, styles could not be loaded on certain templates. This has been fixed.

## v3.1.0 (2019-08-19)

### TODO

- After updating to 3.1.0, you need to carry out the setting for automatically publishing reviews in the **Plugin overview » Feedback » Settings** menu.

### Added

- Guest reviews are now possible.
- Guest customers can now save a nickname for each review.
- We added a honeypot to prevent spam.

### Changed

- The settings for automatically releasing reviews have been expanded.

### Fixed

- The user interface has been improved.
- The plugin's performance has been improved.

## v3.0.0 (2019-08-12)

### Added

- The plugin is now compatible with the ShopBuilder.
- The number of item reviews per page can now be specified via the widget settings.
- The size of the rating stars can now be specified via the widget settings.
- The field “aggregateRatings”, which contains the average rating of an item, has been added to the Schema.org markup.

### Changed

- The feedback plugin has been redesigned as 2 ShopBuilder widgets. The widget **Item review** makes it possible for customers to write reviews that are displayed in the online store. The widget **Star rating** provides the average rating of the reviews on the single item view.
- The plugin settings have mostly been relocated to the 2 ShopBuilder widgets.
- The feedback plugin is now open source.
- The former container links have been removed, with the exception of “Feedback category ratings”.

### Fixed

- When submitting a review, the star rating was only displayed after reloading the page. This has been fixed.
- Reviews without a star rating could not be submitted. This has been fixed.

## v2.0.0 (2019-04-29)

### FEATURE

- Add support for IO 4.0.0.

## v1.4.1 (2019-01-30)

### Fix

- Due to changes to the plentymarkets core funcitonality, feedback ratings were not correctly displayed.

## v1.4.0 (2019-01-10)

### CHANGE

- Filters and sorting options can now be deactivated in the configuration.

## v1.3.1 (2018-10-01)

### Fix

- Due to an error, feedback facets were not displayed. This has been fixed.

### CHANGE

- Texts of the plugin can now be translated via the CMS » Multilingualism menu.

## v1.3.0 (2018-07-31)

### CHANGE

- The plugin was modified to incorporate the Vue.js framework.

## v1.2.1 (2018-07-05)

### Fix

- Migrated guest reviews are now displayed.

## v1.2.0 (2018-05-24)

### Fix

- If reviews have been edited by customer, they keep their visibility status. Now the configuration value is considered.

### CHANGE

- h1-Tag changed to div-tag
- The last name of the author is now shortened

### FEATURE

- If an item has no rating, the star rating will be hidden by default in the category. These can be reactivated in the configuration.

## v1.1.1 (2018-03-08)

### Fix

- Due to an error feedback ratings could not be filtered in the category view of Ceres version 2.2.2 or higher. This has been fixed.

## v1.1.0 (2017-12-01)

### Change

- Ceres 2 compatibility

## v1.0.3 (2017-11-08)

### Added

- Existing feedbacks with ratings of up to 10 stars can now be migrated. For further information, refer to <a href="https://knowledge.plentymarkets.com/en/omni-channel/online-store/managing-feedbacks#100" target="_blank">Migrating customer reviews</a>.
- Attributes are now displayed in the title row of a customer review for the variation the customer review was added for.
- It is possible to filter by ratings in the category view.
- It is possible to sort items by ratings in the category view.
- The ratings of an item can now be displayed for items in the category view. To do so, go to the **Plugins » Content** menu and in the **Feedback category ratings** area, activate the **Category item list: Before prices container** container.

## v1.0.2 (2017-10-26)

### Added

- Migrate existing feedbacks
- Add option to allow adding feedbacks without ratings
- Add option to allow adding feedbacks only for items that were purchased


## v1.0.1 (2017-10-11)

### Fix

- Fix build issue

## v1.0.0 (2017-10-10)

### Added

- Initial program files. Logged in users can enter feedbacks for items and save comments in the online store (e.g. Ceres). Store managers can make customer feedbacks visible/invisible, delete feedbacks or write an answer.
