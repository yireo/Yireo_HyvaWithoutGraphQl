<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\Template;
use Throwable;
use Yireo\HyvaWithoutGraphQl\Model\SliderRequestNormalizer;

/**
 * Translates the block arguments of a Hyvä product slider into the query parameters of the slider
 * controller. This mirrors the parameter handling that used to be inlined in the original
 * `Magento_Theme::elements/slider.phtml` template.
 */
class SliderParameters implements ArgumentInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly SliderRequestNormalizer $sliderRequestNormalizer
    ) {
    }

    /**
     * @param Template $block
     * @return array<string, string>
     */
    public function getRequestParameters(Template $block): array
    {
        $type = (string)$block->getData('type');
        $type = in_array($type, SliderRequestNormalizer::LINK_TYPES, true) ? $type : '';

        $parameters = $this->sliderRequestNormalizer->normalize([
            'skus' => $this->getSkus($block, $type),
            'category_ids' => (string)$block->getData('category_ids'),
            'price_from' => $block->getData('price_from'),
            'price_to' => $block->getData('price_to'),
            'page_size' => $block->getData('page_size'),
            'sort_attribute' => $block->getData('sort_attribute'),
            'sort_direction' => $block->getData('sort_direction'),
            'type' => $type,
            'filters' => (string)$block->getData('product_filters'),
        ]);

        return [
            'skus' => implode(',', $parameters['skus']),
            'category_ids' => implode(',', $parameters['category_ids']),
            'price_from' => $parameters['price_from'] === null ? '' : (string)$parameters['price_from'],
            'price_to' => $parameters['price_to'] === null ? '' : (string)$parameters['price_to'],
            'page_size' => (string)$parameters['page_size'],
            'sort_attribute' => $parameters['sort_attribute'],
            'sort_direction' => $parameters['sort_direction'],
            'type' => $parameters['type'],
            'filters' => $parameters['filters'],
        ];
    }

    private function getSkus(Template $block, string $type): string
    {
        if ($type === '') {
            return (string)$block->getData('product_skus');
        }

        if ($type === 'crosssell') {
            return $this->getCartItemSkus();
        }

        $product = $block->getData('product');
        if ($product === null && method_exists($block, 'getProduct')) {
            $product = $block->getProduct();
        }

        return $product ? (string)$product->getSku() : '';
    }

    private function getCartItemSkus(): string
    {
        try {
            $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
        } catch (Throwable) {
            return '';
        }

        $skus = [];
        foreach ($items as $item) {
            $skus[] = (string)$item->getSku();
        }

        return implode(',', $skus);
    }
}
