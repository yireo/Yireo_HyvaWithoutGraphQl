<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Reviews;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Review\Model\ResourceModel\Rating\CollectionFactory as RatingCollectionFactory;
use Magento\Review\Model\ResourceModel\Rating\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Yireo\HyvaWithoutGraphQl\Service\ReviewSaver;

/**
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 */
class SaveTransactionTest extends AbstractController
{
    /**
     * @var array<int, array{is_active: int, stores: int[]}>
     */
    private array $originalRatings = [];

    protected function tearDown(): void
    {
        $this->restoreRatings();
        $this->_objectManager->removeSharedInstance(ReviewFactory::class);
        $this->_objectManager->removeSharedInstance(ReviewSaver::class);
        parent::tearDown();
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testFailureAfterVotesRollsBackReviewAndVotes(): void
    {
        $ratings = $this->activateRatings();

        $review = $this->getMockBuilder(Review::class)
            ->setConstructorArgs($this->getConstructorArguments(Review::class))
            ->onlyMethods(['aggregate'])
            ->getMock();
        $review->method('aggregate')->willThrowException(new RuntimeException('Aggregation failed'));

        $reviewFactory = $this->createMock(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);
        $this->_objectManager->removeSharedInstance(ReviewSaver::class);
        $this->_objectManager->addSharedInstance($reviewFactory, ReviewFactory::class);

        $reviewCount = $this->countRows('review');
        $voteCount = $this->countRows('rating_option_vote');
        $transactionLevel = $this->getConnection()->getTransactionLevel();

        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue([
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
            'sku' => 'simple',
            'nickname' => 'Transaction Tester',
            'summary' => 'Rolled back',
            'text' => 'This review should never be stored.',
            'ratings' => $ratings,
        ]);
        $this->dispatch('hyva-without-graphql/reviews/save');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(500, $response->getHttpResponseCode());
        $this->assertSame($transactionLevel, $this->getConnection()->getTransactionLevel());
        $this->assertSame($reviewCount, $this->countRows('review'));
        $this->assertSame($voteCount, $this->countRows('rating_option_vote'));
    }

    /**
     * @return array<int, int>
     */
    private function activateRatings(): array
    {
        $storeId = (int)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId();
        $collection = Bootstrap::getObjectManager()->get(RatingCollectionFactory::class)->create();
        $collection->setPageSize(2)->setCurPage(1);

        $connection = $this->getConnection();
        $ratings = [];
        foreach ($collection as $rating) {
            $ratingId = (int)$rating->getId();
            $select = $connection->select()
                ->from($connection->getTableName('rating_store'), ['store_id'])
                ->where('rating_id = ?', $ratingId);
            $this->originalRatings[$ratingId] = [
                'is_active' => (int)$rating->getIsActive(),
                'stores' => array_map('intval', $connection->fetchCol($select)),
            ];

            $rating->setStores([$storeId])->setIsActive(1)->save();
            $ratings[$ratingId] = $this->getOptionId($ratingId);
        }

        $this->assertCount(2, $ratings);

        return $ratings;
    }

    private function restoreRatings(): void
    {
        $collection = Bootstrap::getObjectManager()->get(RatingCollectionFactory::class)->create();
        $collection->addFieldToFilter('rating_id', ['in' => array_keys($this->originalRatings)]);

        foreach ($collection as $rating) {
            $original = $this->originalRatings[(int)$rating->getId()];
            $rating->setStores($original['stores'])->setIsActive($original['is_active'])->save();
        }

        $this->originalRatings = [];
    }

    private function getOptionId(int $ratingId): int
    {
        $collection = Bootstrap::getObjectManager()->get(OptionCollectionFactory::class)->create();
        $collection->addRatingFilter($ratingId)->setPositionOrder();

        return (int)$collection->getFirstItem()->getId();
    }

    /**
     * @param class-string $className
     * @return array<int, mixed>
     */
    private function getConstructorArguments(string $className): array
    {
        $arguments = [];
        foreach ((new ReflectionMethod($className, '__construct'))->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            $type = $parameter->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $type);
            $arguments[] = $this->_objectManager->get($type->getName());
        }

        return $arguments;
    }

    private function countRows(string $table): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($connection->getTableName($table), ['COUNT(*)']);

        return (int)$connection->fetchOne($select);
    }

    private function getConnection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }
}
