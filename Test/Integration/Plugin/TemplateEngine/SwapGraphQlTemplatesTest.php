<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Plugin\TemplateEngine;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\TemplateEngine\Php;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\HyvaWithoutGraphQl\Plugin\TemplateEngine\SwapGraphQlTemplates;

/**
 * Verifies that the DI wiring of the plugin actually carries the expected template maps.
 *
 * @magentoAppArea frontend
 */
class SwapGraphQlTemplatesTest extends TestCase
{
    public function testSliderTemplateIsReplaced(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $plugin = $objectManager->get(SwapGraphQlTemplates::class);

        $result = $plugin->beforeRender(
            $objectManager->get(Php::class),
            $objectManager->create(Template::class),
            '/theme/Magento_Theme/templates/elements/slider.phtml'
        );

        $this->assertStringEndsWith(
            'Yireo/HyvaWithoutGraphQl/view/frontend/templates/elements/slider.phtml',
            str_replace('\\', '/', $result[1])
        );
    }

    public function testUnrelatedTemplateIsUntouched(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $plugin = $objectManager->get(SwapGraphQlTemplates::class);

        $result = $plugin->beforeRender(
            $objectManager->get(Php::class),
            $objectManager->create(Template::class),
            '/theme/Magento_Theme/templates/html/header.phtml'
        );

        $this->assertSame('/theme/Magento_Theme/templates/html/header.phtml', $result[1]);
    }

    /**
     * @dataProvider appendTemplateProvider
     */
    public function testProxyTemplateIsAppended(string $fileName, string $expectedEndpoint): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $plugin = $objectManager->get(SwapGraphQlTemplates::class);

        $block = $objectManager->create(Template::class);
        $result = $plugin->afterRender(
            $objectManager->get(Php::class),
            '<script>fetch(BASE_URL + "graphql")</script>',
            $block,
            $fileName
        );

        $this->assertStringContainsString($expectedEndpoint, $result);
    }

    /**
     * @return array<string, string[]>
     */
    public static function appendTemplateProvider(): array
    {
        return [
            'recently viewed products' => [
                '/theme/Magento_Catalog/templates/product/widget/viewed/js/recently-viewed-products.phtml',
                'window.initRecentlyViewedProductsComponent = function',
            ],
            'customer review list' => [
                '/theme/Magento_Review/templates/customer/list.phtml',
                'window.initReviewList = function',
            ],
        ];
    }
}
