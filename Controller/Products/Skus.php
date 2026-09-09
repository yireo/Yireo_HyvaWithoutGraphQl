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
 * Replaces the GraphQL `products(filter: {sku: {in: []}})` query of the recently viewed products
 * widget.
 */
class Skus implements HttpGetActionInterface
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
            'page_size' => $this->request->getParam('page_size'),
        ]);

        $products = $this->productProvider->getBySkus($params['skus'], $params['page_size']);

        return $this->jsonResultBuilder->create([
            'items' => $this->productDataFormatter->formatMultiple($products),
        ]);
    }
}
