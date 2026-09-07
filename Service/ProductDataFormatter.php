<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Service;

use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Converts a product into the same structure as the GraphQL `products.items` response, so that the
 * Hyvä templates keep working without any changes to their rendering logic.
 */
class ProductDataFormatter
{
    private const XML_PATH_URL_SUFFIX = 'catalog/seo/product_url_suffix';

    public function __construct(
        private readonly ImageFactory $imageFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param Product[] $products
     * @return array<int, array<string, mixed>>
     */
    public function formatMultiple(array $products): array
    {
        $items = [];
        foreach ($products as $product) {
            $items[] = $this->format($product);
        }

        return $items;
    }

    /**
     * @param Product $product
     * @return array<string, mixed>
     */
    public function format(Product $product): array
    {
        return [
            'id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'name' => (string)$product->getName(),
            'url_key' => (string)$product->getData('url_key'),
            'url_suffix' => $this->getUrlSuffix(),
            'visibility' => (int)$product->getVisibility(),
            'status' => (int)$product->getStatus(),
            'small_image' => $this->getImage($product, 'product_small_image'),
            'price_range' => [
                'minimum_price' => [
                    'regular_price' => $this->getPrice($product, RegularPrice::PRICE_CODE),
                    'final_price' => $this->getPrice($product, FinalPrice::PRICE_CODE),
                ],
            ],
        ];
    }

    /**
     * @param Product $product
     * @param string $imageId
     * @return array{url: string, label: string}
     */
    public function getImage(Product $product, string $imageId): array
    {
        $image = $this->imageFactory->create()->init($product, $imageId);
        $label = $product->getData($imageId === 'product_base_image' ? 'image_label' : 'small_image_label');

        return [
            'url' => (string)$image->getUrl(),
            'label' => (string)($label ?: $product->getName()),
        ];
    }

    /**
     * @param Product $product
     * @param string $priceCode
     * @return array{value: float, currency: string}
     */
    private function getPrice(Product $product, string $priceCode): array
    {
        return [
            'value' => (float)$product->getPriceInfo()->getPrice($priceCode)->getAmount()->getValue(),
            'currency' => $this->getCurrencyCode(),
        ];
    }

    private function getUrlSuffix(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_URL_SUFFIX,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );
    }

    private function getCurrencyCode(): string
    {
        return (string)$this->storeManager->getStore()->getCurrentCurrencyCode();
    }
}
