<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Model;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Throwable;

/**
 * Decides which product attributes the slider `product_filters` may filter on: only those that the
 * store already exposes for layered navigation, search filtering or sorting.
 */
class FilterableAttributeAllowlist
{
    private const ALWAYS_ALLOWED = ['sku', 'name', 'price'];

    private const ALLOWING_PROPERTIES = ['is_filterable', 'is_filterable_in_search', 'used_for_sort_by'];

    public function __construct(
        private readonly EavConfig $eavConfig
    ) {
    }

    public function isAllowed(string $attributeCode): bool
    {
        if (in_array($attributeCode, self::ALWAYS_ALLOWED, true)) {
            return true;
        }

        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        } catch (Throwable) {
            return false;
        }

        if (!$attribute->getId()) {
            return false;
        }

        foreach (self::ALLOWING_PROPERTIES as $property) {
            if ((int)$attribute->getData($property) > 0) {
                return true;
            }
        }

        return false;
    }
}
