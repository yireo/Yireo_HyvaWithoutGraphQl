<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Products;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use Yireo\HyvaWithoutGraphQl\Model\SliderSignature;

/**
 * @magentoAppArea frontend
 */
class SliderTest extends AbstractController
{
    /**
     * The price index is only updated after a committed transaction, so DB isolation is disabled.
     *
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testSkuFilterReturnsMatchingProduct(): void
    {
        $this->assertSame(['simple'], $this->dispatchSigned(['skus' => 'simple', 'page_size' => '4']));
    }

    public function testUnsignedRequestIsForbidden(): void
    {
        $this->dispatchSlider(['skus' => 'simple', 'page_size' => '4']);

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(403, $response->getHttpResponseCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
    }

    public function testTamperedRequestIsForbidden(): void
    {
        $params = ['skus' => 'simple', 'page_size' => '4'];
        $params['sig'] = $this->sign($params);
        $params['page_size'] = '100';
        $this->dispatchSlider($params);

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(403, $response->getHttpResponseCode());
    }

    public function testInvalidSignatureIsForbidden(): void
    {
        $this->dispatchSlider(['skus' => 'simple', 'page_size' => '4', 'sig' => str_repeat('a', 64)]);

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(403, $response->getHttpResponseCode());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testPageSizeIsCapped(): void
    {
        $this->assertLessThanOrEqual(100, count($this->dispatchSigned(['page_size' => '99999'])));
    }

    /**
     * An invalid sort attribute must not reach the collection, otherwise it would raise an error.
     *
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testInvalidSortAttributeFallsBackToPosition(): void
    {
        $this->dispatchSigned(['sort_attribute' => 'no_such_attribute', 'page_size' => '4']);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testUnknownCustomFilterIsIgnored(): void
    {
        $this->dispatchSigned([
            'filters' => 'no_such_attribute: { eq: "whatever" }',
            'page_size' => '4',
        ]);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testFilterOnNonFilterableAttributeIsIgnored(): void
    {
        $this->assertSame(['simple'], $this->dispatchSigned([
            'skus' => 'simple',
            'filters' => 'cost: { eq: "987654" }',
            'page_size' => '4',
        ]));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testFilterOnAllowedAttributeIsApplied(): void
    {
        $this->assertSame([], $this->dispatchSigned([
            'skus' => 'simple',
            'filters' => 'sku: { eq: "does-not-exist" }',
            'page_size' => '4',
        ]));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/products_crosssell.php
     */
    public function testCrosssellIgnoresClientSkus(): void
    {
        $this->assertSame([], $this->dispatchSigned([
            'skus' => 'simple_with_cross',
            'type' => 'crosssell',
            'page_size' => '4',
        ]));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/products_crosssell.php
     */
    public function testCrosssellUsesCartSkus(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $product = $objectManager->get(ProductRepositoryInterface::class)->get('simple_with_cross');

        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        $quote->setStoreId(1);
        $quote->addProduct($product, 1);
        $objectManager->get(CartRepositoryInterface::class)->save($quote);

        $checkoutSession = $objectManager->get(CheckoutSession::class);
        $checkoutSession->setQuoteId($quote->getId());

        try {
            $this->assertSame(['simple'], $this->dispatchSigned([
                'type' => 'crosssell',
                'page_size' => '4',
            ]));
        } finally {
            $checkoutSession->clearQuote();
            $objectManager->get(CartRepositoryInterface::class)->delete($quote);
        }
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     * @magentoDataFixture Magento/Catalog/_files/inactive_category.php
     */
    public function testInactiveCategoryReturnsNoProducts(): void
    {
        $this->assertSame([], $this->dispatchSigned(['category_ids' => '111', 'page_size' => '4']));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     */
    public function testUnknownCategoryReturnsNoProducts(): void
    {
        $this->assertSame([], $this->dispatchSigned(['category_ids' => '987654', 'page_size' => '4']));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     * @magentoDataFixture Magento/Catalog/_files/inactive_category.php
     */
    public function testActiveCategoryInStoreRootReturnsProducts(): void
    {
        $this->assertSame(['simple333'], $this->dispatchSigned(['category_ids' => '333', 'page_size' => '4']));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     * @magentoDataFixture Magento/Catalog/_files/inactive_category.php
     */
    public function testInactiveCategoryIsRemovedFromMultipleCategories(): void
    {
        $this->assertSame(['simple333'], $this->dispatchSigned(['category_ids' => '111,333', 'page_size' => '4']));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     */
    public function testCategoryOutsideStoreRootReturnsNoProducts(): void
    {
        $this->assertSame([], $this->dispatchSigned(['category_ids' => '1', 'page_size' => '4']));
    }

    /**
     * @param array<string, string> $params
     * @return string[]
     */
    private function dispatchSigned(array $params): array
    {
        $params['sig'] = $this->sign($params);
        $this->dispatchSlider($params);

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);

        return array_column($data['items'], 'sku');
    }

    /**
     * @param array<string, string> $params
     * @return void
     */
    private function dispatchSlider(array $params): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams($params);
        $this->dispatch('hyva-without-graphql/products/slider');
    }

    /**
     * @param array<string, string> $params
     * @return string
     */
    private function sign(array $params): string
    {
        return Bootstrap::getObjectManager()->get(SliderSignature::class)->sign($params);
    }
}
