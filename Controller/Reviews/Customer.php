<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Controller\Reviews;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Yireo\HyvaWithoutGraphQl\Config\Config;
use Yireo\HyvaWithoutGraphQl\Controller\JsonResultBuilder;
use Yireo\HyvaWithoutGraphQl\Service\CustomerReviewProvider;

/**
 * Replaces the GraphQL `customer.reviews` query of the customer account review list.
 */
class Customer implements HttpGetActionInterface
{
    private const MAX_PAGE_SIZE = 50;
    private const DEFAULT_PAGE_SIZE = 5;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly JsonResultBuilder $jsonResultBuilder,
        private readonly CustomerSession $customerSession,
        private readonly CustomerReviewProvider $customerReviewProvider
    ) {
    }

    public function execute(): Json
    {
        if (!$this->config->isEnabled()) {
            return $this->jsonResultBuilder->createError((string)__('Not available.'), 404);
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId === 0) {
            return $this->jsonResultBuilder->createError((string)__('You are not signed in.'), 401);
        }

        $reviews = $this->customerReviewProvider->getReviews(
            $customerId,
            $this->getCurrentPage(),
            $this->getPageSize()
        );

        return $this->jsonResultBuilder->create($reviews);
    }

    private function getCurrentPage(): int
    {
        return max(1, (int)$this->request->getParam('current_page', 1));
    }

    private function getPageSize(): int
    {
        $pageSize = (int)$this->request->getParam('page_size', self::DEFAULT_PAGE_SIZE);
        if ($pageSize < 1) {
            return self::DEFAULT_PAGE_SIZE;
        }

        return min($pageSize, self::MAX_PAGE_SIZE);
    }
}
