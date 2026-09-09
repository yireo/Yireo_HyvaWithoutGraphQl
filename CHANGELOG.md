# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Plugin `SwapGraphQlTemplates` on `Magento\Framework\View\TemplateEngine\Php` that replaces or
  extends Hyvä templates containing a GraphQL call.
- JS proxies for `initReviewList::getReviewsList`, `initReviewForm::placeReview` and
  `initRecentlyViewedProductsComponent::fetchProducts`.
- GraphQL-free replacement of `Magento_Theme::elements/slider.phtml`.
- Frontend controllers `hyva-without-graphql/reviews/customer`, `hyva-without-graphql/reviews/save`,
  `hyva-without-graphql/products/skus` and `hyva-without-graphql/products/slider`.
- Store configuration `yireo_hyva_without_graph_ql/settings/enabled` to switch the module off per
  store.
- Unit tests for the filter parser, the request normalizer and the template plugin, plus
  integration tests for the four controllers.

### Changed
- Added `php` and `magento/module-theme` requirements to `composer.json`.
- Synchronised `MODULE.json` sequence with `etc/module.xml` and enabled PHPStan level 5 and
  PHPCS severity 6 in CI.
- Made the GraphQL marker detection in `SwapGraphQlTemplates` case-insensitive.
- Documented the CSRF bypass in `Reviews\Save` and clarified `CustomerReviewProvider` PHPDoc.
