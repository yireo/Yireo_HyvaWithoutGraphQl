<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Controller\Products;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Psr\Log\LoggerInterface;
use Throwable;
use Yireo\HyvaWithoutGraphQl\Config\Config;
use Yireo\HyvaWithoutGraphQl\Controller\JsonResultBuilder;
use Yireo\HyvaWithoutGraphQl\Model\SliderRequestNormalizer;
use Yireo\HyvaWithoutGraphQl\Model\SliderSignature;
use Yireo\HyvaWithoutGraphQl\Service\ProductDataFormatter;
use Yireo\HyvaWithoutGraphQl\Service\ProductProvider;

/**
 * Replaces the GraphQL `products` query of the Hyvä product slider. Only parameter sets signed by
 * the slider template are accepted; the SKUs of cross-sell sliders are taken from the current cart.
 */
class Slider implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly JsonResultBuilder $jsonResultBuilder,
        private readonly SliderRequestNormalizer $sliderRequestNormalizer,
        private readonly SliderSignature $sliderSignature,
        private readonly ProductProvider $productProvider,
        private readonly ProductDataFormatter $productDataFormatter,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        if (!$this->config->isEnabled()) {
            return $this->jsonResultBuilder->createError((string)__('Not available.'), 404);
        }

        $rawParams = $this->getRawParams();
        $signature = $this->request->getParam(SliderSignature::SIGNATURE_PARAMETER);
        if (!is_string($signature) || !$this->sliderSignature->verify($rawParams, $signature)) {
            return $this->jsonResultBuilder->createError((string)__('Invalid request signature.'), 403);
        }

        $params = $this->sliderRequestNormalizer->normalize($rawParams);
        if ($params['type'] === 'crosssell') {
            $params['skus'] = $this->getCartItemSkus();
        }

        $products = $this->productProvider->getForSlider($params);

        return $this->jsonResultBuilder->create([
            'items' => $this->productDataFormatter->formatMultiple($products),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRawParams(): array
    {
        $params = [];
        foreach (SliderSignature::PARAMETER_KEYS as $key) {
            $value = $this->request->getParam($key);
            if ($value !== null) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @return string[]
     */
    private function getCartItemSkus(): array
    {
        try {
            $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
        } catch (Throwable $throwable) {
            $this->logger->debug('Unable to load cart items for cross-sell slider', [
                'exception' => $throwable->getMessage(),
            ]);

            return [];
        }

        $skus = [];
        foreach ($items as $item) {
            $sku = (string)$item->getProduct()->getData('sku');
            $skus[] = $sku !== '' ? $sku : (string)$item->getSku();
        }

        return array_values(array_unique(array_filter($skus, static fn (string $sku): bool => $sku !== '')));
    }
}
