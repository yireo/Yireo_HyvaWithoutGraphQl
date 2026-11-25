<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Service;

use Magento\Review\Model\Rating;
use Magento\Review\Model\ResourceModel\Rating\CollectionFactory as RatingCollectionFactory;
use Magento\Review\Model\Review;
use Yireo\HyvaWithoutGraphQl\Exception\ReviewInputException;

class RatingValidator
{
    public function __construct(
        private readonly RatingCollectionFactory $ratingCollectionFactory
    ) {
    }

    /**
     * @param array<int, int> $ratings
     * @param int $storeId
     * @return array<int, int>
     * @throws ReviewInputException
     */
    public function validate(array $ratings, int $storeId): array
    {
        $allowedOptions = $this->getAllowedOptions($storeId);

        foreach ($ratings as $ratingId => $optionId) {
            if (!in_array($optionId, $allowedOptions[$ratingId] ?? [], true)) {
                throw new ReviewInputException(__('The selected rating is not valid.'));
            }
        }

        if (array_diff_key($allowedOptions, $ratings) !== []) {
            throw new ReviewInputException(__('Please select one of each of the ratings above.'));
        }

        return $ratings;
    }

    /**
     * @param int $storeId
     * @return array<int, int[]>
     */
    private function getAllowedOptions(int $storeId): array
    {
        $collection = $this->ratingCollectionFactory->create();
        $collection->addEntityFilter(Review::ENTITY_PRODUCT_CODE)
            ->setActiveFilter(true)
            ->setStoreFilter($storeId)
            ->addOptionToItems();

        $allowedOptions = [];
        /** @var Rating $rating */
        foreach ($collection as $rating) {
            $optionIds = [];
            foreach ((array)$rating->getOptions() as $option) {
                $optionIds[] = (int)$option->getId();
            }

            $allowedOptions[(int)$rating->getId()] = $optionIds;
        }

        return $allowedOptions;
    }
}
