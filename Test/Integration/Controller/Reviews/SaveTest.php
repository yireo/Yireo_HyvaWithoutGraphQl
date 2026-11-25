<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Reviews;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Review\Model\Rating;
use Magento\Review\Model\ResourceModel\Rating\CollectionFactory as RatingCollectionFactory;
use Magento\Review\Model\ResourceModel\Rating\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use Yireo\HyvaWithoutGraphQl\Service\ReviewSaver;

/**
 * @magentoAppArea frontend
 */
class SaveTest extends AbstractController
{
    private const PRODUCT_NOT_FOUND = 'The product could not be found.';

    protected function tearDown(): void
    {
        $this->_objectManager->removeSharedInstance(FormKeyValidator::class);
        $this->_objectManager->removeSharedInstance(ReviewSaver::class);
        parent::tearDown();
    }

    /**
     * The test framework always injects a valid form key into POST requests, so the validator is stubbed.
     */
    public function testInvalidFormKeyIsRejected(): void
    {
        $formKeyValidator = $this->createMock(FormKeyValidator::class);
        $formKeyValidator->method('validate')->willReturn(false);
        $this->_objectManager->addSharedInstance($formKeyValidator, FormKeyValidator::class);

        $reviewSaver = $this->createMock(ReviewSaver::class);
        $reviewSaver->expects($this->never())->method('save');
        $this->_objectManager->addSharedInstance($reviewSaver, ReviewSaver::class);

        $this->postReview(['sku' => 'simple']);

        $response = $this->getHttpResponse();
        $this->assertSame(403, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Invalid Form Key. Please refresh the page.', $data['messages'][0]);
    }

    /**
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testUnknownSkuIsRejected(): void
    {
        $this->postReview($this->getReviewData('does-not-exist'));
        $this->assertErrorResponse(400, self::PRODUCT_NOT_FOUND);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDbIsolation enabled
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testDisabledProductIsRejected(): void
    {
        $this->updateProductAttributes(['status' => Status::STATUS_DISABLED]);

        $this->postReview($this->getReviewData());
        $this->assertErrorResponse(400, self::PRODUCT_NOT_FOUND);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDbIsolation enabled
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testNotVisibleProductIsRejected(): void
    {
        $this->updateProductAttributes(['visibility' => Visibility::VISIBILITY_NOT_VISIBLE]);

        $this->postReview($this->getReviewData());
        $this->assertErrorResponse(400, self::PRODUCT_NOT_FOUND);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testGuestReviewIsStoredAsPending(): void
    {
        $this->postReview($this->getReviewData());

        $response = $this->getHttpResponse();
        $this->assertSame(200, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);

        $review = $this->getReviewByNickname('Integration Tester');
        $this->assertNotEmpty($review->getId());
        $this->assertSame((int)Review::STATUS_PENDING, (int)$review->getStatusId());
        $this->assertSame('Solid product', $review->getTitle());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default_store catalog/review/active 0
     */
    public function testDisabledReviewsAreRejected(): void
    {
        $this->postReview($this->getReviewData());

        $this->assertSame(400, $this->getHttpResponse()->getHttpResponseCode());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDbIsolation enabled
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testValidRatingsAreStored(): void
    {
        [$firstRating, $secondRating] = $this->activateRatings();
        $ratings = [
            $firstRating->getId() => $this->getOptionId($firstRating),
            $secondRating->getId() => $this->getOptionId($secondRating),
        ];

        $this->postReview($this->getReviewData() + ['ratings' => $ratings]);

        $this->assertSame(200, $this->getHttpResponse()->getHttpResponseCode());

        $review = $this->getReviewByNickname('Integration Tester');
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName('rating_option_vote'), ['rating_id', 'option_id'])
            ->where('review_id = ?', (int)$review->getId());
        $votes = $connection->fetchPairs($select);

        $this->assertEquals($ratings, $votes);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDbIsolation enabled
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testOptionOfOtherRatingIsRejected(): void
    {
        [$firstRating, $secondRating] = $this->activateRatings();
        $ratings = [
            $firstRating->getId() => $this->getOptionId($secondRating),
            $secondRating->getId() => $this->getOptionId($secondRating),
        ];

        $this->assertNoReviewIsStored($ratings);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDbIsolation enabled
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testUnknownOptionIsRejected(): void
    {
        [$firstRating, $secondRating] = $this->activateRatings();
        $ratings = [
            $firstRating->getId() => 999999,
            $secondRating->getId() => $this->getOptionId($secondRating),
        ];

        $this->assertNoReviewIsStored($ratings);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDbIsolation enabled
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testMissingRequiredRatingIsRejected(): void
    {
        [$firstRating] = $this->activateRatings();
        $ratings = [$firstRating->getId() => $this->getOptionId($firstRating)];

        $this->assertNoReviewIsStored($ratings);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testInternalErrorIsNotDisclosed(): void
    {
        $reviewSaver = $this->createMock(ReviewSaver::class);
        $reviewSaver->method('save')->willThrowException(new LocalizedException(__('Internal SQL details')));
        $this->_objectManager->addSharedInstance($reviewSaver, ReviewSaver::class);

        $this->postReview($this->getReviewData());
        $this->assertErrorResponse(500, 'We can\'t post your review right now.');
    }

    /**
     * @param array<int|string, int> $ratings
     * @return void
     */
    private function assertNoReviewIsStored(array $ratings): void
    {
        $before = $this->countReviews();

        $this->postReview($this->getReviewData() + ['ratings' => $ratings]);

        $response = $this->getHttpResponse();
        $this->assertSame(400, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertSame($before, $this->countReviews());
    }

    private function assertErrorResponse(int $httpCode, string $message): void
    {
        $response = $this->getHttpResponse();
        $this->assertSame($httpCode, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertSame($message, $data['messages'][0]);
    }

    /**
     * @return Rating[]
     */
    private function activateRatings(): array
    {
        $storeId = (int)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId();
        $collection = Bootstrap::getObjectManager()->get(RatingCollectionFactory::class)->create();
        $collection->setPageSize(2)->setCurPage(1);

        $ratings = [];
        foreach ($collection as $rating) {
            $rating->setStores([$storeId])->setIsActive(1)->save();
            $ratings[] = $rating;
        }

        $this->assertCount(2, $ratings);

        return $ratings;
    }

    private function getOptionId(Rating $rating): int
    {
        $collection = Bootstrap::getObjectManager()->get(OptionCollectionFactory::class)->create();
        $collection->addRatingFilter($rating->getId())->setPositionOrder();

        return (int)$collection->getFirstItem()->getId();
    }

    /**
     * @param array<string, int> $attributes
     * @return void
     */
    private function updateProductAttributes(array $attributes): void
    {
        $productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        $productId = (int)$productRepository->get('simple')->getId();
        Bootstrap::getObjectManager()->get(ProductAction::class)->updateAttributes([$productId], $attributes, 0);
        $productRepository->cleanCache();
    }

    private function countReviews(): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($connection->getTableName('review'), ['COUNT(*)']);

        return (int)$connection->fetchOne($select);
    }

    private function getConnection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function getReviewByNickname(string $nickname): Review
    {
        $collection = Bootstrap::getObjectManager()->get(ReviewCollectionFactory::class)->create();
        $collection->addFieldToFilter('nickname', $nickname);

        return $collection->getFirstItem();
    }

    /**
     * @param string $sku
     * @return array<string, string>
     */
    private function getReviewData(string $sku = 'simple'): array
    {
        return [
            'form_key' => Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey(),
            'sku' => $sku,
            'nickname' => 'Integration Tester',
            'summary' => 'Solid product',
            'text' => 'This review was stored without using GraphQL.',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    private function postReview(array $data): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue($data);
        $this->dispatch('hyva-without-graphql/reviews/save');
    }

    private function getHttpResponse(): HttpResponse
    {
        /** @var HttpResponse $response */
        $response = $this->getResponse();

        return $response;
    }
}
