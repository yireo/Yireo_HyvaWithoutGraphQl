<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Products;

use Laminas\Http\Header\HeaderInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 */
class SkusTest extends AbstractController
{
    /**
     * The price index is only updated after a committed transaction, so DB isolation is disabled.
     *
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testKnownSkuIsReturnedInGraphQlShape(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams(['skus' => 'simple']);
        $this->dispatch('hyva-without-graphql/products/skus');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);

        $item = $data['items'][0];
        $this->assertSame('simple', $item['sku']);
        $this->assertArrayHasKey('url_key', $item);
        $this->assertArrayHasKey('url_suffix', $item);
        $this->assertArrayHasKey('url', $item['small_image']);
        $this->assertArrayHasKey('label', $item['small_image']);
        $this->assertArrayHasKey('value', $item['price_range']['minimum_price']['regular_price']);
        $this->assertArrayHasKey('currency', $item['price_range']['minimum_price']['final_price']);
    }

    public function testUnknownSkuReturnsEmptyList(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams(['skus' => 'does-not-exist']);
        $this->dispatch('hyva-without-graphql/products/skus');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertSame(['items' => []], json_decode($response->getBody(), true));
    }

    public function testResponseIsNotCacheable(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams(['skus' => 'does-not-exist']);
        $this->dispatch('hyva-without-graphql/products/skus');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $header = $response->getHeader('Cache-Control');
        $this->assertInstanceOf(HeaderInterface::class, $header);
        $this->assertStringContainsString('no-store', $header->getFieldValue());
    }

    /**
     * @magentoConfigFixture default_store yireo_hyva_without_graph_ql/settings/enabled 0
     */
    public function testDisabledModuleReturnsNotFound(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setParams(['skus' => 'simple']);
        $this->dispatch('hyva-without-graphql/products/skus');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(404, $response->getHttpResponseCode());
    }
}
