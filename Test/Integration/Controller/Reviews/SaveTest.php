<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Reviews;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 */
class SaveTest extends AbstractController
{
    public function testMissingFormKeyIsRejected(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue(['sku' => 'simple']);
        $this->dispatch('hyva-without-graphql/reviews/save');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(403, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
    }

    /**
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testUnknownSkuIsRejected(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue([
            'form_key' => $this->getFormKey(),
            'sku' => 'does-not-exist',
            'nickname' => 'Tester',
            'summary' => 'Summary',
            'text' => 'Some review text',
        ]);
        $this->dispatch('hyva-without-graphql/reviews/save');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(400, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('product', strtolower($data['messages'][0]));
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default_store catalog/review/active 1
     * @magentoConfigFixture default_store catalog/review/allow_guest 1
     */
    public function testGuestReviewIsStoredAsPending(): void
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue([
            'form_key' => $this->getFormKey(),
            'sku' => 'simple',
            'nickname' => 'Integration Tester',
            'summary' => 'Solid product',
            'text' => 'This review was stored without using GraphQL.',
        ]);
        $this->dispatch('hyva-without-graphql/reviews/save');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());

        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);

        $collection = Bootstrap::getObjectManager()->get(ReviewCollectionFactory::class)->create();
        $collection->addFieldToFilter('nickname', 'Integration Tester');
        $review = $collection->getFirstItem();

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
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue([
            'form_key' => $this->getFormKey(),
            'sku' => 'simple',
            'nickname' => 'Integration Tester',
            'summary' => 'Solid product',
            'text' => 'This review was stored without using GraphQL.',
        ]);
        $this->dispatch('hyva-without-graphql/reviews/save');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $this->assertSame(400, $response->getHttpResponseCode());
    }

    private function getFormKey(): string
    {
        return Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey();
    }
}
