<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Products;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 */
class SkusTest extends AbstractController
{
    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testKnownSkuIsReturnedInGraphQlShape(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['skus' => 'simple']);
        $this->dispatch('hyva-without-graphql/products/skus');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());

        $data = json_decode($this->getResponse()->getBody(), true);
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
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['skus' => 'does-not-exist']);
        $this->dispatch('hyva-without-graphql/products/skus');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
        $this->assertSame(['items' => []], json_decode($this->getResponse()->getBody(), true));
    }

    public function testResponseIsNotCacheable(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['skus' => 'does-not-exist']);
        $this->dispatch('hyva-without-graphql/products/skus');

        $this->assertStringContainsString(
            'no-store',
            (string)$this->getResponse()->getHeader('Cache-Control')?->getFieldValue()
        );
    }

    /**
     * @magentoConfigFixture default_store yireo_hyva_without_graph_ql/settings/enabled 0
     */
    public function testDisabledModuleReturnsNotFound(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['skus' => 'simple']);
        $this->dispatch('hyva-without-graphql/products/skus');

        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
    }
}
