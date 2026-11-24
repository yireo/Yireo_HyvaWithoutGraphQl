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
use Magento\Framework\DB\Sql\Expression;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Yireo\HyvaWithoutGraphQl\Model\FilterableAttributeAllowlist;
use Yireo\HyvaWithoutGraphQl\Model\ProductFilterParser;

/**
 * Loads the products that the Hyvä product sliders and the recently viewed products widget used to
 * fetch through GraphQL.
 */
class ProductProvider
{
    public const MAX_LINK_SOURCE_SKUS = 20;

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
        private readonly FilterableAttributeAllowlist $filterableAttributeAllowlist,
        private readonly StoreManagerInterface $storeManager,
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
        if ($limit > 0) {
            $connection = $collection->getConnection();
            $collection->getSelect()->order(new Expression($connection->quoteInto('FIELD(e.sku, ?)', $skus)));
            $collection->setPageSize($limit);
            $collection->setCurPage(1);
        }

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
        if (!$this->applyCategoryFilter($collection, $params['category_ids'])) {
            return [];
        }

        $this->applyPriceFilter($collection, $params['price_from'], $params['price_to']);
        $this->applyCustomFilters($collection, (string)$params['filters']);

        $collection->addAttributeToSort((string)$params['sort_attribute'], (string)$params['sort_direction']);

        /** @var Product[] $items */
        $items = $collection->getItems();

        return array_values($items);
    }

    /**
     * @param array<string, mixed> $params
     * @return Product[]
     */
    private function getLinkedProducts(array $params): array
    {
        $sourceSkus = $this->getExistingSkus(array_slice($params['skus'], 0, self::MAX_LINK_SOURCE_SKUS));

        $linkedSkus = [];
        foreach ($sourceSkus as $sku) {
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
            $this->logger->debug('Unable to load product links for slider', [
                'type' => $type,
                'sku' => $sku,
                'exception' => $throwable->getMessage(),
            ]);

            return [];
        }

        usort($links, static fn ($left, $right): int => (int)$left->getPosition() <=> (int)$right->getPosition());

        return array_map(static fn ($link): string => (string)$link->getLinkedProductSku(), $links);
    }

    /**
     * @param string[] $skus
     * @return string[]
     */
    private function getExistingSkus(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $collection = $this->createBaseCollection();
        $collection->addAttributeToFilter('sku', ['in' => $skus]);
        $existingSkus = array_map('strval', $collection->getColumnValues('sku'));

        return array_values(array_intersect($skus, $existingSkus));
    }

    private function createBaseCollection(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addStoreFilter();
        $collection->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED]);
        $collection->setVisibility($this->visibility->getVisibleInCatalogIds());

        return $collection;
    }

    private function createCollection(): Collection
    {
        $collection = $this->createBaseCollection();
        $collection->addAttributeToSelect($this->catalogConfig->getProductAttributes());
        $collection->addAttributeToSelect(self::ADDITIONAL_ATTRIBUTES);
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addTaxPercents();
        $collection->addUrlRewrite();

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
     * @return bool False when categories were requested but none of them may be shown
     */
    private function applyCategoryFilter(Collection $collection, array $categoryIds): bool
    {
        if ($categoryIds === []) {
            return true;
        }

        $categories = $this->getVisibleCategories($categoryIds);
        if ($categories === []) {
            return false;
        }

        if (count($categories) > 1) {
            $collection->addCategoriesFilter(['in' => array_keys($categories)]);

            return true;
        }

        $collection->addCategoryFilter(reset($categories));

        return true;
    }

    /**
     * @param int[] $categoryIds
     * @return array<int, Category>
     */
    private function getVisibleCategories(array $categoryIds): array
    {
        try {
            $store = $this->storeManager->getStore();
        } catch (Throwable $throwable) {
            $this->logger->debug('Unable to load store for slider categories', [
                'exception' => $throwable->getMessage(),
            ]);

            return [];
        }

        $rootCategoryId = (string)$store->getRootCategoryId();
        $categories = [];
        foreach ($categoryIds as $categoryId) {
            try {
                $category = $this->categoryRepository->get($categoryId, (int)$store->getId());
            } catch (Throwable $throwable) {
                $this->logger->debug('Unable to load slider category', [
                    'category_id' => $categoryId,
                    'exception' => $throwable->getMessage(),
                ]);

                continue;
            }

            if (!$category instanceof Category || !$category->getIsActive()) {
                continue;
            }

            if (!in_array($rootCategoryId, explode('/', (string)$category->getPath()), true)) {
                continue;
            }

            $categories[(int)$category->getId()] = $category;
        }

        return $categories;
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
            if (!$this->filterableAttributeAllowlist->isAllowed($attributeCode)) {
                $this->logger->debug('Ignoring slider filter on non-filterable attribute', [
                    'attribute' => $attributeCode,
                ]);

                continue;
            }

            try {
                $collection->addAttributeToFilter($attributeCode, $condition);
            } catch (Throwable $throwable) {
                $this->logger->debug('Unable to filter slider on attribute', [
                    'attribute' => $attributeCode,
                    'exception' => $throwable->getMessage(),
                ]);
            }
        }
    }
}
