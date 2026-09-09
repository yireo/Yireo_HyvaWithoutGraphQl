<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Endpoints implements ArgumentInterface
{
    private const FRONT_NAME = 'hyva-without-graphql';

    public function __construct(
        private readonly UrlInterface $url
    ) {
    }

    public function getCustomerReviewsUrl(): string
    {
        return $this->url->getUrl(self::FRONT_NAME . '/reviews/customer');
    }

    public function getSaveReviewUrl(): string
    {
        return $this->url->getUrl(self::FRONT_NAME . '/reviews/save');
    }

    public function getProductsBySkusUrl(): string
    {
        return $this->url->getUrl(self::FRONT_NAME . '/products/skus');
    }

    public function getSliderProductsUrl(): string
    {
        return $this->url->getUrl(self::FRONT_NAME . '/products/slider');
    }
}
