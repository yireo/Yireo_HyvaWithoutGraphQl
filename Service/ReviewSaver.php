<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Helper\Data as ReviewHelper;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;
use Magento\Review\Model\Review;
use Magento\Review\Model\Review\Config as ReviewConfig;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;
use Yireo\HyvaWithoutGraphQl\Exception\ReviewInputException;

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
        private readonly StoreManagerInterface $storeManager,
        private readonly RatingValidator $ratingValidator,
        private readonly EventManager $eventManager
    ) {
    }

    /**
     * @param string $sku
     * @param string $nickname
     * @param string $summary
     * @param string $text
     * @param array<int, int> $ratings
     * @return void
     * @throws ReviewInputException
     * @throws Throwable
     */
    public function save(string $sku, string $nickname, string $summary, string $text, array $ratings): void
    {
        if (!$this->reviewConfig->isEnabled()) {
            throw new ReviewInputException(__('Product reviews are not available.'));
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId === 0 && !$this->reviewHelper->getIsGuestAllowToWrite()) {
            throw new ReviewInputException(__('Only registered users can write reviews.'));
        }

        $storeId = (int)$this->storeManager->getStore()->getId();
        $product = $this->getProduct($sku, $storeId);

        /** @var Review $review */
        $review = $this->reviewFactory->create();
        $review->setData([
            'nickname' => $nickname,
            'title' => $summary,
            'detail' => $text,
        ]);

        $validation = $review->validate();
        if ($validation !== true) {
            throw new ReviewInputException(
                __(is_array($validation) ? (string)reset($validation) : 'We can\'t post your review right now.')
            );
        }

        $ratings = $this->ratingValidator->validate($ratings, $storeId);

        $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue($product->getId())
            ->setStatusId(Review::STATUS_PENDING)
            ->setCustomerId($customerId ?: null)
            ->setStoreId($storeId)
            ->setStores([$storeId]);

        $connection = $this->reviewResource->getConnection();
        $connection->beginTransaction();

        try {
            $this->reviewResource->save($review);

            foreach ($ratings as $ratingId => $optionId) {
                $this->ratingFactory->create()
                    ->setRatingId($ratingId)
                    ->setReviewId($review->getId())
                    ->setCustomerId($customerId ?: null)
                    ->addOptionVote($optionId, $product->getId());
            }

            $review->aggregate();
            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }

    /**
     * @param string $sku
     * @param int $storeId
     * @return Product
     * @throws ReviewInputException
     */
    private function getProduct(string $sku, int $storeId): Product
    {
        $this->eventManager->dispatch('review_controller_product_init_before', ['controller_action' => null]);

        try {
            $product = $this->productRepository->get($sku, false, $storeId);
        } catch (NoSuchEntityException) {
            throw $this->createProductNotFoundException();
        }

        if (!$product instanceof Product || !$this->isProductAvailable($product)) {
            throw $this->createProductNotFoundException();
        }

        try {
            $this->eventManager->dispatch('review_controller_product_init', ['product' => $product]);
            $this->eventManager->dispatch(
                'review_controller_product_init_after',
                ['product' => $product, 'controller_action' => null]
            );
        } catch (LocalizedException) {
            throw $this->createProductNotFoundException();
        }

        return $product;
    }

    private function isProductAvailable(Product $product): bool
    {
        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();
        $websiteIds = array_map('intval', (array)$product->getWebsiteIds());

        return in_array($websiteId, $websiteIds, true)
            && (int)$product->getStatus() === Status::STATUS_ENABLED
            && $product->isVisibleInCatalog()
            && $product->isVisibleInSiteVisibility();
    }

    private function createProductNotFoundException(): ReviewInputException
    {
        return new ReviewInputException(__('The product could not be found.'));
    }
}
