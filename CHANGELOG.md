# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 26 November 2026
### Added
- Slider requests are now signed with an HMAC derived from the deployment crypt key; missing or invalid signatures are rejected with a 403 (flush the caches after upgrading so cached slider markup carries a signature).
- Allowlist of product attributes that slider filters may use; filters on other attributes are ignored.

### Changed
- Declared the dependency on `Magento_Eav` and moved PHPUnit to `require-dev`.

### Fixed
- The slider endpoint could be abused for heavy queries: the number of filters, list values, SKUs, category IDs and linked source products is now capped, short `like` wildcards are rejected and the page size is applied in SQL.
- Sliders only use active categories below the store root category, validated in a single query, and keep the requested category mode.
- Cross-sell sliders read the cart SKUs from the checkout session instead of the URL, and no longer drop SKU "0".
- A SKU in a different case no longer takes a slider slot without being shown.
- The slider shows an error message when its request fails.
- Reviews could be posted for disabled, invisible or other-website products and with inactive or foreign ratings; these are now rejected with a generic message.
- The review form key is validated through the Magento CSRF validator, and malformed form keys, review fields or rating IDs get a 403 or 400 response instead of an error.
- Internal error details are no longer exposed in review responses but logged instead.
- A failing review save is now rolled back completely, so no partial votes are left behind.
- Observers of the review product init events no longer fail on a missing controller action.
- Client-side messages are escaped before being rendered as HTML (XSS).
- Templates are only swapped when their path matches on a path boundary.
- The admin ACL resource is nested under Stores > Settings > Configuration, fixing an invalid merged `acl.xml`.

## [0.0.1] - 13 October 2026
### Added
- Plugin `SwapGraphQlTemplates` on `Magento\Framework\View\TemplateEngine\Php` that replaces or extends Hyvä templates containing a GraphQL call.
- JS proxies for `initReviewList::getReviewsList`, `initReviewForm::placeReview` and `initRecentlyViewedProductsComponent::fetchProducts`.
- GraphQL-free replacement of `Magento_Theme::elements/slider.phtml`.
- Frontend controllers `hyva-without-graphql/reviews/customer`, `hyva-without-graphql/reviews/save`, `hyva-without-graphql/products/skus` and `hyva-without-graphql/products/slider`.
- Store configuration `yireo_hyva_without_graph_ql/settings/enabled` to switch the module off per store.
- Unit tests for the filter parser, the request normalizer and the template plugin, plus integration tests for the four controllers.

### Changed
- Added `php` and `magento/module-theme` requirements to `composer.json`.
- Synchronised `MODULE.json` sequence with `etc/module.xml` and enabled PHPStan level 5 and
  PHPCS severity 6 in CI.
- Made the GraphQL marker detection in `SwapGraphQlTemplates` case-insensitive.
- Documented the CSRF bypass in `Reviews\Save` and clarified `CustomerReviewProvider` PHPDoc.
