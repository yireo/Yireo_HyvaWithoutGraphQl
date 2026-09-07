# Yireo_HyvaWithoutGraphQl

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.1%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/-/badges/release.svg)
<!-- badges.specs.end -->


> Replaces the GraphQL calls of various Hyvä templates with regular Magento controllers.

## What it does

A few Hyvä templates fetch their data from the GraphQL endpoint. This module replaces those calls
with regular Magento frontend controllers, so that the storefront no longer depends on the GraphQL
API for these features.

| Hyvä template | Alpine component | Method | How |
|---|---|---|---|
| `Magento_Review::customer/list.phtml` | `initReviewList` | `getReviewsList` | JS proxy |
| `Magento_Catalog::product/widget/viewed/js/recently-viewed-products.phtml` | `initRecentlyViewedProductsComponent` | `fetchProducts` | JS proxy |
| `Magento_Review::form.phtml` | `initReviewForm` | `placeReview` | JS proxy |
| `Magento_Theme::elements/slider.phtml` | `initSliderComponent<uniqid>` | `getProducts` | template replacement |

The first three are left intact: an extra template is rendered right after the original one, which
wraps the global component factory and swaps out that single method. The deprecated GraphQL product
slider is replaced entirely, because its query is built from block arguments that only exist
server side.

Both are done from a single plugin on `Magento\Framework\View\TemplateEngine\Php`, so widgets and
dynamically named blocks are covered as well. The template maps are DI arguments of
`Yireo\HyvaWithoutGraphQl\Plugin\TemplateEngine\SwapGraphQlTemplates`, so individual entries can be
removed in a project.

The controllers answer with exactly the same data structure as the GraphQL responses they replace,
which is why the Hyvä templates keep rendering unchanged.

| Route | Method | Replaces |
|---|---|---|
| `hyva-without-graphql/reviews/customer` | GET | `customer.reviews` |
| `hyva-without-graphql/reviews/save` | POST | `createProductReview` |
| `hyva-without-graphql/products/skus` | GET | `products(filter: {sku: {in: []}})` |
| `hyva-without-graphql/products/slider` | GET | the slider `products` query |

### Installation
Install this package via composer (provided you have properly configured our composer API first):
```bash
composer require yireo/magento2-hyva-without-graph-ql
```

Next, enable this module:
```bash
bin/magento module:enable Yireo_HyvaWithoutGraphQl
bin/magento setup:upgrade
```

### Configuration
`Stores > Configuration > General > Hyvä Without GraphQL > Settings > Enabled`
(`yireo_hyva_without_graph_ql/settings/enabled`, default `1`). When switched off, the templates are
left untouched and the controllers answer with a 404.

### Security

- `reviews/save` validates the Magento form key itself and answers with JSON instead of a redirect,
  and validates reCAPTCHA for form id `product_review` when it is configured.
- `reviews/customer` only ever returns the reviews of the customer in the current session.
- The slider parameters travel over the URL. The page size is capped at 100 and the sort attribute
  is limited to a whitelist.
- All responses are sent with `Cache-Control: no-store`, since they depend on store, currency,
  customer group or customer session.

### Notes and limitations

- A slider block that is configured with a custom `graphql_query` argument is no longer honoured.
  The structured arguments (`product_skus`, `category_ids`, `price_from`, `price_to`, `page_size`,
  `sort_attribute`, `sort_direction`, `type`) and `product_filters` are supported.
  `product_filters` is parsed by `Yireo\HyvaWithoutGraphQl\Model\ProductFilterParser`, which
  understands `eq`, `neq`, `in`, `nin`, `from`, `to`, `gt`, `gteq`, `lt`, `lteq`, `like` and
  `match`. Anything else is ignored and logged.
- The recently viewed proxy only comes into play when
  `catalog/recently_products/synchronize_with_backend` is enabled. Without it, Hyvä renders the
  widget straight from browser storage and never calls GraphQL in the first place.
- The customer review list no longer waits for the GraphQL `signin_token` of the private content
  section; the session cookie is enough for a regular controller.
- `price_range.minimum_price.base_price` stays absent, matching the original GraphQL query.
- The replacement slider template fixes two typos of the original: `getprevButtonClasses` (now
  `getPrevButtonClasses`) and `fillerSlideNumer` (now `fillerSlideNumber`). It also uses `x-text`
  instead of `x-html` for the product name.

## Current status

<!-- badges.test.start -->
[![Static Tests](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/badges/main/pipeline.svg?job=static-tests)](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/-/pipelines)
[![Unit Tests](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/badges/main/pipeline.svg?job=unit-tests)](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/-/pipelines)
[![Integration Tests](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/badges/main/pipeline.svg?job=integration-tests)](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/-/pipelines)
[![Playwright](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/badges/main/pipeline.svg?job=playwright)](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/-/pipelines)
[![DI Compilation](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/badges/main/pipeline.svg?job=compile)](https://gitlab.yireo.com/loki-checkout/Yireo_HyvaWithoutGraphQl/-/pipelines)
<!-- badges.test.end -->

