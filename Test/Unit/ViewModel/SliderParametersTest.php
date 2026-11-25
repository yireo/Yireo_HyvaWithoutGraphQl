<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Unit\ViewModel;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\View\Element\Template;
use PHPUnit\Framework\TestCase;
use Yireo\HyvaWithoutGraphQl\Model\SliderRequestNormalizer;
use Yireo\HyvaWithoutGraphQl\Model\SliderSignature;
use Yireo\HyvaWithoutGraphQl\ViewModel\SliderParameters;

class SliderParametersTest extends TestCase
{
    private SliderSignature $sliderSignature;
    private SliderParameters $sliderParameters;

    protected function setUp(): void
    {
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn('secret');
        $this->sliderSignature = new SliderSignature($deploymentConfig);
        $this->sliderParameters = new SliderParameters(new SliderRequestNormalizer(), $this->sliderSignature);
    }

    public function testParametersAreSigned(): void
    {
        $parameters = $this->sliderParameters->getRequestParameters($this->createBlock([
            'product_skus' => '24-MB01, 24-MB02',
            'page_size' => 4,
            'price_from' => 10,
            'product_filters' => 'color: { in: ["yellow"] }',
        ]));

        $this->assertSame('24-MB01,24-MB02', $parameters['skus']);
        $this->assertArrayHasKey(SliderSignature::SIGNATURE_PARAMETER, $parameters);
        $this->assertTrue($this->sliderSignature->verify(
            $this->simulateRequest($parameters),
            $parameters[SliderSignature::SIGNATURE_PARAMETER]
        ));
    }

    public function testPageSizeIsSentAsString(): void
    {
        $parameters = $this->sliderParameters->getRequestParameters($this->createBlock(['page_size' => 4]));

        $this->assertSame('4', $parameters['page_size']);
    }

    public function testCrosssellSendsNoSkus(): void
    {
        $parameters = $this->sliderParameters->getRequestParameters($this->createBlock([
            'type' => 'crosssell',
            'product_skus' => '24-MB01',
        ]));

        $this->assertSame('crosssell', $parameters['type']);
        $this->assertSame('', $parameters['skus']);
    }

    /**
     * @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    private function simulateRequest(array $parameters): array
    {
        parse_str(http_build_query($parameters), $received);
        unset($received[SliderSignature::SIGNATURE_PARAMETER]);

        return $received;
    }

    /**
     * @param array<string, mixed> $data
     * @return Template
     */
    private function createBlock(array $data): Template
    {
        $block = $this->createMock(Template::class);
        $block->method('getData')->willReturnCallback(
            static fn (string $key): mixed => $data[$key] ?? null
        );

        return $block;
    }
}
