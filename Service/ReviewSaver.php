<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Helper\Data as ReviewHelper;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;
use Magento\Review\Model\Review;
use Magento\Review\Model\Review\Config as ReviewConfig;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Stores a product review, mirroring the behaviour of `Magento\Review\Controller\Product\Post`.
 */
class ReviewSaver
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ReviewFactory $reviewFactory,
        private readonly ReviewResource $reviewResource,
        private readonly RatingFactory $ratingFactory,
        private readonly ReviewConfig $reviewConfig,
        private readonly ReviewHelper $reviewHelper,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param string $sku
     * @param string $nickname
     * @param string $summary
     * @param string $text
     * @param array<int, int> $ratings
     * @return void
     * @throws LocalizedException
     */
    public function save(string $sku, string $nickname, string $summary, string $text, array $ratings): void
    {
        if (!$this->reviewConfig->isEnabled()) {
            throw new LocalizedException(__('Product reviews are not available.'));
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId === 0 && !$this->reviewHelper->getIsGuestAllowToWrite()) {
            throw new LocalizedException(__('Only registered users can write reviews.'));
        }

        $product = $this->getProduct($sku);
        $storeId = (int)$this->storeManager->getStore()->getId();

        /** @var Review $review */
        $review = $this->reviewFactory->create();
        $review->setData([
            'nickname' => $nickname,
            'title' => $summary,
            'detail' => $text,
        ]);

        $validation = $review->validate();
        if ($validation !== true) {
            throw new LocalizedException(
                __(is_array($validation) ? (string)reset($validation) : 'We can\'t post your review right now.')
            );
        }

        $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue($product->getId())
            ->setStatusId(Review::STATUS_PENDING)
            ->setCustomerId($customerId ?: null)
            ->setStoreId($storeId)
            ->setStores([$storeId]);

        $this->reviewResource->save($review);

        foreach ($ratings as $ratingId => $optionId) {
            $this->ratingFactory->create()
                ->setRatingId($ratingId)
                ->setReviewId($review->getId())
                ->setCustomerId($customerId ?: null)
                ->addOptionVote($optionId, $product->getId());
        }

        $review->aggregate();
    }

    private function getProduct(string $sku): ProductInterface
    {
        try {
            return $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('The product could not be found.'));
        }
    }
}
