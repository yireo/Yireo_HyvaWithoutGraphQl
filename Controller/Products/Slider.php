<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Controller\Products;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Yireo\HyvaWithoutGraphQl\Config\Config;
use Yireo\HyvaWithoutGraphQl\Controller\JsonResultBuilder;
use Yireo\HyvaWithoutGraphQl\Model\SliderRequestNormalizer;
use Yireo\HyvaWithoutGraphQl\Service\ProductDataFormatter;
use Yireo\HyvaWithoutGraphQl\Service\ProductProvider;

/**
 * Replaces the GraphQL `products` query of the Hyvä product slider.
 */
class Slider implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly JsonResultBuilder $jsonResultBuilder,
        private readonly SliderRequestNormalizer $sliderRequestNormalizer,
        private readonly ProductProvider $productProvider,
        private readonly ProductDataFormatter $productDataFormatter
    ) {
    }

    public function execute(): Json
    {
        if (!$this->config->isEnabled()) {
            return $this->jsonResultBuilder->createError((string)__('Not available.'), 404);
        }

        $params = $this->sliderRequestNormalizer->normalize([
            'skus' => $this->request->getParam('skus', ''),
            'category_ids' => $this->request->getParam('category_ids', ''),
            'price_from' => $this->request->getParam('price_from'),
            'price_to' => $this->request->getParam('price_to'),
            'page_size' => $this->request->getParam('page_size'),
            'sort_attribute' => $this->request->getParam('sort_attribute'),
            'sort_direction' => $this->request->getParam('sort_direction'),
            'type' => $this->request->getParam('type'),
            'filters' => $this->request->getParam('filters'),
        ]);

        $products = $this->productProvider->getForSlider($params);

        return $this->jsonResultBuilder->create([
            'items' => $this->productDataFormatter->formatMultiple($products),
        ]);
    }
}
