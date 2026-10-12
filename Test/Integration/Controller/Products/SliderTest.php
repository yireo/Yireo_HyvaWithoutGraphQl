<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Products;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
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
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams(['skus' => 'simple', 'page_size' => '4']);
        $this->dispatch('hyva-without-graphql/products/slider');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame(['simple'], array_column($data['items'], 'sku'));
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testPageSizeIsCapped(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams(['page_size' => '99999']);
        $this->dispatch('hyva-without-graphql/products/slider');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertLessThanOrEqual(100, count($data['items']));
    }

    /**
     * An invalid sort attribute must not reach the collection, otherwise it would raise an error.
     *
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testInvalidSortAttributeFallsBackToPosition(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams(['sort_attribute' => 'no_such_attribute', 'page_size' => '4']);
        $this->dispatch('hyva-without-graphql/products/slider');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testUnknownCustomFilterIsIgnored(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams([
            'filters' => 'no_such_attribute: { eq: "whatever" }',
            'page_size' => '4',
        ]);
        $this->dispatch('hyva-without-graphql/products/slider');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());
    }
}
