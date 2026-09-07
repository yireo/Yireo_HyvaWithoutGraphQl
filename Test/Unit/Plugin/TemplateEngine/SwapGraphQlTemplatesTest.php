<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Unit\Plugin\TemplateEngine;

use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\FileSystem as ViewFileSystem;
use Magento\Framework\View\TemplateEngine\Php;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Yireo\HyvaWithoutGraphQl\Config\Config;
use Yireo\HyvaWithoutGraphQl\Plugin\TemplateEngine\SwapGraphQlTemplates;

class SwapGraphQlTemplatesTest extends TestCase
{
    private const REPLACE_TEMPLATES = [
        'slider' => [
            'match' => 'Magento_Theme/templates/elements/slider.phtml',
            'template' => 'Yireo_HyvaWithoutGraphQl::elements/slider.phtml',
        ],
    ];

    private const APPEND_TEMPLATES = [
        'review_form' => [
            'match' => 'Magento_Review/templates/form.phtml',
            'template' => 'Yireo_HyvaWithoutGraphQl::proxy/review-form.phtml',
        ],
    ];

    private Config&MockObject $config;
    private ViewFileSystem&MockObject $viewFileSystem;
    private Php&MockObject $subject;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->viewFileSystem = $this->createMock(ViewFileSystem::class);
        $this->subject = $this->createMock(Php::class);
    }

    public function testBeforeRenderReplacesMatchingTemplate(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->viewFileSystem->method('getTemplateFileName')
            ->with('Yireo_HyvaWithoutGraphQl::elements/slider.phtml')
            ->willReturn('/app/code/Yireo/HyvaWithoutGraphQl/view/frontend/templates/elements/slider.phtml');

        $result = $this->createPlugin()->beforeRender(
            $this->subject,
            $block,
            '/vendor/hyva-themes/theme/Magento_Theme/templates/elements/slider.phtml'
        );

        $this->assertSame(
            '/app/code/Yireo/HyvaWithoutGraphQl/view/frontend/templates/elements/slider.phtml',
            $result[1]
        );
    }

    public function testBeforeRenderKeepsUnknownTemplate(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->viewFileSystem->expects($this->never())->method('getTemplateFileName');

        $result = $this->createPlugin()->beforeRender($this->subject, $block, '/some/other/template.phtml');

        $this->assertSame('/some/other/template.phtml', $result[1]);
    }

    public function testBeforeRenderKeepsTemplateWhenDisabled(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $this->config->method('isEnabled')->willReturn(false);
        $this->viewFileSystem->expects($this->never())->method('getTemplateFileName');

        $result = $this->createPlugin()->beforeRender(
            $this->subject,
            $block,
            '/vendor/hyva-themes/theme/Magento_Theme/templates/elements/slider.phtml'
        );

        $this->assertSame('/vendor/hyva-themes/theme/Magento_Theme/templates/elements/slider.phtml', $result[1]);
    }

    public function testBeforeRenderKeepsTemplateWhenReplacementCannotBeResolved(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->viewFileSystem->method('getTemplateFileName')->willReturn(false);

        $result = $this->createPlugin()->beforeRender(
            $this->subject,
            $block,
            '/vendor/hyva-themes/theme/Magento_Theme/templates/elements/slider.phtml'
        );

        $this->assertSame('/vendor/hyva-themes/theme/Magento_Theme/templates/elements/slider.phtml', $result[1]);
    }

    public function testAfterRenderAppendsProxyTemplate(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $block = $this->createMock(Template::class);
        $block->method('getTemplateFile')
            ->with('Yireo_HyvaWithoutGraphQl::proxy/review-form.phtml')
            ->willReturn('/proxy/review-form.phtml');
        $block->method('fetchView')->with('/proxy/review-form.phtml')->willReturn('<script>proxy</script>');

        $result = $this->createPlugin()->afterRender(
            $this->subject,
            '<script>fetch(BASE_URL + "graphql")</script>',
            $block,
            '/vendor/hyva-themes/theme/Magento_Review/templates/form.phtml'
        );

        $this->assertSame('<script>fetch(BASE_URL + "graphql")</script><script>proxy</script>', $result);
    }

    public function testAfterRenderAppendsProxyTemplateForMixedCaseGraphQlMarker(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $block = $this->createMock(Template::class);
        $block->method('getTemplateFile')
            ->with('Yireo_HyvaWithoutGraphQl::proxy/review-form.phtml')
            ->willReturn('/proxy/review-form.phtml');
        $block->method('fetchView')->with('/proxy/review-form.phtml')->willReturn('<script>proxy</script>');

        $result = $this->createPlugin()->afterRender(
            $this->subject,
            '<script>fetch(BASE_URL + "GraphQL")</script>',
            $block,
            '/vendor/hyva-themes/theme/Magento_Review/templates/form.phtml'
        );

        $this->assertSame('<script>fetch(BASE_URL + "GraphQL")</script><script>proxy</script>', $result);
    }

    public function testAfterRenderSkipsOutputWithoutGraphQlCall(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $block = $this->createMock(Template::class);
        $block->expects($this->never())->method('fetchView');

        $result = $this->createPlugin()->afterRender(
            $this->subject,
            '<div>Only registered users can write reviews.</div>',
            $block,
            '/vendor/hyva-themes/theme/Magento_Review/templates/form.phtml'
        );

        $this->assertSame('<div>Only registered users can write reviews.</div>', $result);
    }

    public function testAfterRenderSkipsUnknownTemplate(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $block = $this->createMock(Template::class);
        $block->expects($this->never())->method('fetchView');

        $result = $this->createPlugin()->afterRender(
            $this->subject,
            '<script>graphql</script>',
            $block,
            '/vendor/hyva-themes/theme/Magento_Review/templates/other.phtml'
        );

        $this->assertSame('<script>graphql</script>', $result);
    }

    public function testAfterRenderSkipsNonTemplateBlock(): void
    {
        $this->config->expects($this->never())->method('isEnabled');
        $block = $this->createMock(BlockInterface::class);

        $result = $this->createPlugin()->afterRender(
            $this->subject,
            '<script>graphql</script>',
            $block,
            '/vendor/hyva-themes/theme/Magento_Review/templates/form.phtml'
        );

        $this->assertSame('<script>graphql</script>', $result);
    }

    public function testAfterRenderSkipsWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $block = $this->createMock(Template::class);
        $block->expects($this->never())->method('fetchView');

        $result = $this->createPlugin()->afterRender(
            $this->subject,
            '<script>graphql</script>',
            $block,
            '/vendor/hyva-themes/theme/Magento_Review/templates/form.phtml'
        );

        $this->assertSame('<script>graphql</script>', $result);
    }

    private function createPlugin(): SwapGraphQlTemplates
    {
        return new SwapGraphQlTemplates(
            $this->config,
            $this->viewFileSystem,
            self::REPLACE_TEMPLATES,
            self::APPEND_TEMPLATES
        );
    }
}
