<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Service;

use Magento\Catalog\Model\Product;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\CollectionFactory as VoteCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\Product\CollectionFactory as ReviewCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Returns the reviews of a single customer in the same structure as the GraphQL
 * `customer.reviews` response.
 */
class CustomerReviewProvider
{
    private const ADDITIONAL_ATTRIBUTES = ['url_key', 'image', 'image_label', 'small_image', 'small_image_label'];

    public function __construct(
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly VoteCollectionFactory $voteCollectionFactory,
        private readonly ProductDataFormatter $productDataFormatter,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @param int $customerId
     * @param int $currentPage
     * @param int $pageSize
     * @return array{items: array<int, array<string, mixed>>, page_info: array<string, int>}
     */
    public function getReviews(int $customerId, int $currentPage, int $pageSize): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        $collection = $this->reviewCollectionFactory->create();
        $collection->addStoreFilter($storeId)
            ->addCustomerFilter($customerId)
            ->setDateOrder();
        $collection->addAttributeToSelect(self::ADDITIONAL_ATTRIBUTES);
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);
        $collection->load();

        $items = [];
        foreach ($collection as $review) {
            $items[] = $this->formatReview($review, $storeId);
        }

        return [
            'items' => $items,
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => max(1, (int)$collection->getLastPageNumber()),
            ],
        ];
    }

    /**
     * @param Product $reviewProduct The product model returned by the review-product collection.
     * @param int $storeId
     * @return array<string, mixed>
     */
    private function formatReview(Product $reviewProduct, int $storeId): array
    {
        return [
            'created_at' => $this->formatDate((string)$reviewProduct->getData('review_created_at')),
            'summary' => (string)$reviewProduct->getData('title'),
            'text' => (string)$reviewProduct->getData('detail'),
            'nickname' => (string)$reviewProduct->getData('nickname'),
            'product' => [
                'name' => (string)$reviewProduct->getName(),
                'sku' => (string)$reviewProduct->getSku(),
                'url_key' => (string)$reviewProduct->getData('url_key'),
                'image' => $this->productDataFormatter->getImage($reviewProduct, 'product_base_image'),
            ],
            'ratings_breakdown' => $this->getRatingsBreakdown((int)$reviewProduct->getData('review_id'), $storeId),
        ];
    }

    /**
     * @param int $reviewId
     * @param int $storeId
     * @return array<int, array{name: string, value: int}>
     */
    private function getRatingsBreakdown(int $reviewId, int $storeId): array
    {
        $votes = $this->voteCollectionFactory->create()
            ->setReviewFilter($reviewId)
            ->addRatingInfo($storeId)
            ->load();

        $breakdown = [];
        foreach ($votes as $vote) {
            $breakdown[] = [
                'name' => (string)$vote->getData('rating_code'),
                'value' => (int)$vote->getData('value'),
            ];
        }

        return $breakdown;
    }

    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        return $this->timezone->date(new \DateTime($date))->format('c');
    }
}
