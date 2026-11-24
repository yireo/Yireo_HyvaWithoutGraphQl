<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Unit\Model;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Yireo\HyvaWithoutGraphQl\Model\FilterableAttributeAllowlist;

class FilterableAttributeAllowlistTest extends TestCase
{
    public function testFixedAttributesAreAllowedWithoutLookup(): void
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects($this->never())->method('getAttribute');
        $allowlist = new FilterableAttributeAllowlist($eavConfig);

        foreach (['sku', 'name', 'price', 'category_id'] as $code) {
            $this->assertTrue($allowlist->isAllowed($code), $code);
        }
    }

    public function testFilterableAttributeIsAllowed(): void
    {
        $this->assertTrue($this->createAllowlist(['is_filterable' => '1'])->isAllowed('color'));
        $this->assertTrue($this->createAllowlist(['is_filterable' => '2'])->isAllowed('color'));
    }

    public function testFilterableInSearchAttributeIsAllowed(): void
    {
        $this->assertTrue($this->createAllowlist(['is_filterable_in_search' => '1'])->isAllowed('color'));
    }

    public function testSortableAttributeIsAllowed(): void
    {
        $this->assertTrue($this->createAllowlist(['used_for_sort_by' => '1'])->isAllowed('color'));
    }

    public function testNonFilterableAttributeIsNotAllowed(): void
    {
        $this->assertFalse($this->createAllowlist([
            'is_filterable' => '0',
            'is_filterable_in_search' => '0',
            'used_for_sort_by' => '0',
        ])->isAllowed('cost'));
    }

    public function testUnknownAttributeIsNotAllowed(): void
    {
        $this->assertFalse($this->createAllowlist(['is_filterable' => '1'], null)->isAllowed('no_such_attribute'));
    }

    public function testLookupFailureIsNotAllowed(): void
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willThrowException(new LocalizedException(__('error')));

        $this->assertFalse((new FilterableAttributeAllowlist($eavConfig))->isAllowed('color'));
    }

    /**
     * @param array<string, string> $data
     * @param int|null $attributeId
     * @return FilterableAttributeAllowlist
     */
    private function createAllowlist(array $data, ?int $attributeId = 42): FilterableAttributeAllowlist
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getId')->willReturn($attributeId);
        $attribute->method('getData')->willReturnCallback(
            static fn (string $key): mixed => $data[$key] ?? null
        );

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->with('catalog_product', $this->anything())->willReturn($attribute);

        return new FilterableAttributeAllowlist($eavConfig);
    }
}
