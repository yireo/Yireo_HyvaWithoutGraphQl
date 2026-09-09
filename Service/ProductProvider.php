<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Service;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductLinkManagementInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Psr\Log\LoggerInterface;
use Throwable;
use Yireo\HyvaWithoutGraphQl\Model\ProductFilterParser;

/**
 * Loads the products that the Hyvä product sliders and the recently viewed products widget used to
 * fetch through GraphQL.
 */
class ProductProvider
{
    private const ADDITIONAL_ATTRIBUTES = [
        'name',
        'url_key',
        'small_image',
        'small_image_label',
        'image',
        'image_label',
        'visibility',
        'status',
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CatalogConfig $catalogConfig,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly Visibility $visibility,
        private readonly ProductLinkManagementInterface $productLinkManagement,
        private readonly ProductFilterParser $productFilterParser,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string[] $skus
     * @param int $limit
     * @return Product[]
     */
    public function getBySkus(array $skus, int $limit = 0): array
    {
        if ($skus === []) {
            return [];
        }

        $collection = $this->createCollection();
        $collection->addAttributeToFilter('sku', ['in' => $skus]);

        $productsBySku = [];
        foreach ($collection as $product) {
            $productsBySku[$product->getSku()] = $product;
        }

        $products = [];
        foreach ($skus as $sku) {
            if (isset($productsBySku[$sku])) {
                $products[] = $productsBySku[$sku];
            }
        }

        return $limit > 0 ? array_slice($products, 0, $limit) : $products;
    }

    /**
     * @param array<string, mixed> $params
     * @return Product[]
     */
    public function getForSlider(array $params): array
    {
        if ($params['type'] !== '') {
            return $this->getLinkedProducts($params);
        }

        $collection = $this->createCollection();
        $collection->setPageSize((int)$params['page_size']);
        $collection->setCurPage(1);

        $this->applySkuFilter($collection, $params['skus']);
        $this->applyCategoryFilter($collection, $params['category_ids']);
        $this->applyPriceFilter($collection, $params['price_from'], $params['price_to']);
        $this->applyCustomFilters($collection, (string)$params['filters']);

        $collection->addAttributeToSort((string)$params['sort_attribute'], (string)$params['sort_direction']);

        return array_values($collection->getItems());
    }

    /**
     * @param array<string, mixed> $params
     * @return Product[]
     */
    private function getLinkedProducts(array $params): array
    {
        $linkedSkus = [];
        foreach ($params['skus'] as $sku) {
            foreach ($this->getLinkedSkus((string)$sku, (string)$params['type']) as $linkedSku) {
                $linkedSkus[] = $linkedSku;
            }
        }

        $linkedSkus = array_values(array_unique($linkedSkus));
        $linkedSkus = array_diff($linkedSkus, $params['skus']);

        return $this->getBySkus(array_values($linkedSkus), (int)$params['page_size']);
    }

    /**
     * @param string $sku
     * @param string $type
     * @return string[]
     */
    private function getLinkedSkus(string $sku, string $type): array
    {
        try {
            $links = $this->productLinkManagement->getLinkedItemsByType($sku, $type);
        } catch (Throwable $throwable) {
            $this->logger->debug(
                sprintf('Unable to load "%s" links for SKU "%s": %s', $type, $sku, $throwable->getMessage())
            );

            return [];
        }

        usort($links, static fn ($left, $right): int => (int)$left->getPosition() <=> (int)$right->getPosition());

        return array_map(static fn ($link): string => (string)$link->getLinkedProductSku(), $links);
    }

    private function createCollection(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect($this->catalogConfig->getProductAttributes());
        $collection->addAttributeToSelect(self::ADDITIONAL_ATTRIBUTES);
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addTaxPercents();
        $collection->addUrlRewrite();
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->visibility->getVisibleInCatalogIds());

        return $collection;
    }

    /**
     * @param Collection $collection
     * @param string[] $skus
     * @return void
     */
    private function applySkuFilter(Collection $collection, array $skus): void
    {
        if ($skus !== []) {
            $collection->addAttributeToFilter('sku', ['in' => $skus]);
        }
    }

    /**
     * @param Collection $collection
     * @param int[] $categoryIds
     * @return void
     */
    private function applyCategoryFilter(Collection $collection, array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        if (count($categoryIds) > 1) {
            $collection->addCategoriesFilter(['in' => $categoryIds]);

            return;
        }

        try {
            $category = $this->categoryRepository->get((int)reset($categoryIds));
        } catch (Throwable $throwable) {
            $this->logger->debug('Unable to load slider category: ' . $throwable->getMessage());

            return;
        }

        if ($category instanceof Category) {
            $collection->addCategoryFilter($category);
        }
    }

    private function applyPriceFilter(Collection $collection, ?float $priceFrom, ?float $priceTo): void
    {
        $condition = [];
        if ($priceFrom !== null) {
            $condition['from'] = $priceFrom;
        }

        if ($priceTo !== null) {
            $condition['to'] = $priceTo;
        }

        if ($condition !== []) {
            $collection->addAttributeToFilter('price', $condition);
        }
    }

    private function applyCustomFilters(Collection $collection, string $filters): void
    {
        foreach ($this->productFilterParser->parse($filters) as $attributeCode => $condition) {
            try {
                $collection->addAttributeToFilter($attributeCode, $condition);
            } catch (Throwable $throwable) {
                $this->logger->debug(
                    sprintf('Unable to filter on attribute "%s": %s', $attributeCode, $throwable->getMessage())
                );
            }
        }
    }
}
