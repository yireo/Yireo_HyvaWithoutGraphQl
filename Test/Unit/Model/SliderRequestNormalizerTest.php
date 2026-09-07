<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Yireo\HyvaWithoutGraphQl\Model\SliderRequestNormalizer;

class SliderRequestNormalizerTest extends TestCase
{
    private SliderRequestNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SliderRequestNormalizer();
    }

    public function testEmptyInputReturnsDefaults(): void
    {
        $this->assertSame(
            [
                'skus' => [],
                'category_ids' => [],
                'price_from' => null,
                'price_to' => null,
                'page_size' => SliderRequestNormalizer::DEFAULT_PAGE_SIZE,
                'sort_attribute' => 'position',
                'sort_direction' => 'ASC',
                'type' => '',
                'filters' => '',
            ],
            $this->normalizer->normalize([])
        );
    }

    public function testSkusAreSplitTrimmedAndDeduplicated(): void
    {
        $result = $this->normalizer->normalize(['skus' => ' 24-MB01 , 24-MB02,24-MB01, ']);

        $this->assertSame(['24-MB01', '24-MB02'], $result['skus']);
    }

    public function testCategoryIdsOnlyAcceptDigits(): void
    {
        $result = $this->normalizer->normalize(['category_ids' => '3,abc,7']);

        $this->assertSame([3, 7], $result['category_ids']);
    }

    public function testPageSizeIsCapped(): void
    {
        $result = $this->normalizer->normalize(['page_size' => 5000]);

        $this->assertSame(SliderRequestNormalizer::MAX_PAGE_SIZE, $result['page_size']);
    }

    public function testInvalidPageSizeFallsBackToDefault(): void
    {
        $this->assertSame(
            SliderRequestNormalizer::DEFAULT_PAGE_SIZE,
            $this->normalizer->normalize(['page_size' => 0])['page_size']
        );
        $this->assertSame(
            SliderRequestNormalizer::DEFAULT_PAGE_SIZE,
            $this->normalizer->normalize(['page_size' => 'many'])['page_size']
        );
    }

    public function testSortAttributeIsWhitelisted(): void
    {
        $this->assertSame('name', $this->normalizer->normalize(['sort_attribute' => 'name'])['sort_attribute']);
        $this->assertSame(
            'position',
            $this->normalizer->normalize(['sort_attribute' => 'cost); DROP TABLE'])['sort_attribute']
        );
    }

    public function testSortDirectionIsWhitelisted(): void
    {
        $this->assertSame('DESC', $this->normalizer->normalize(['sort_direction' => 'desc'])['sort_direction']);
        $this->assertSame('ASC', $this->normalizer->normalize(['sort_direction' => 'sideways'])['sort_direction']);
    }

    public function testTypeIsWhitelisted(): void
    {
        $this->assertSame('crosssell', $this->normalizer->normalize(['type' => 'crosssell'])['type']);
        $this->assertSame('', $this->normalizer->normalize(['type' => 'nonsense'])['type']);
    }

    public function testPricesAreCastToFloatOrNull(): void
    {
        $result = $this->normalizer->normalize(['price_from' => '10.5', 'price_to' => 'abc']);

        $this->assertSame(10.5, $result['price_from']);
        $this->assertNull($result['price_to']);
    }
}
