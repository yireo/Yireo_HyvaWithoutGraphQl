<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Products;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 */
class SliderTest extends AbstractController
{
    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testSkuFilterReturnsMatchingProduct(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['skus' => 'simple', 'page_size' => '4']);
        $this->dispatch('hyva-without-graphql/products/slider');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());

        $data = json_decode($this->getResponse()->getBody(), true);
        $this->assertSame(['simple'], array_column($data['items'], 'sku'));
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testPageSizeIsCapped(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['page_size' => '99999']);
        $this->dispatch('hyva-without-graphql/products/slider');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());

        $data = json_decode($this->getResponse()->getBody(), true);
        $this->assertLessThanOrEqual(100, count($data['items']));
    }

    /**
     * An invalid sort attribute must not reach the collection, otherwise it would raise an error.
     *
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testInvalidSortAttributeFallsBackToPosition(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['sort_attribute' => 'no_such_attribute', 'page_size' => '4']);
        $this->dispatch('hyva-without-graphql/products/slider');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testUnknownCustomFilterIsIgnored(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams([
            'filters' => 'no_such_attribute: { eq: "whatever" }',
            'page_size' => '4',
        ]);
        $this->dispatch('hyva-without-graphql/products/slider');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
    }
}
