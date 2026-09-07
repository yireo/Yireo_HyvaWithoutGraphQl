<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration\Controller\Reviews;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 */
class CustomerTest extends AbstractController
{
    public function testGuestIsRejected(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('hyva-without-graphql/reviews/customer');

        $this->assertSame(401, $this->getResponse()->getHttpResponseCode());

        $data = json_decode($this->getResponse()->getBody(), true);
        $this->assertFalse($data['success']);
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testSignedInCustomerWithoutReviewsGetsEmptyList(): void
    {
        Bootstrap::getObjectManager()->get(CustomerSession::class)->setCustomerId(1);

        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('hyva-without-graphql/reviews/customer');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());

        $data = json_decode($this->getResponse()->getBody(), true);
        $this->assertSame([], $data['items']);
        $this->assertSame(5, $data['page_info']['page_size']);
        $this->assertSame(1, $data['page_info']['current_page']);
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testPageSizeIsCapped(): void
    {
        Bootstrap::getObjectManager()->get(CustomerSession::class)->setCustomerId(1);

        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParams(['page_size' => '9999']);
        $this->dispatch('hyva-without-graphql/reviews/customer');

        $data = json_decode($this->getResponse()->getBody(), true);
        $this->assertSame(50, $data['page_info']['page_size']);
    }

    protected function tearDown(): void
    {
        Bootstrap::getObjectManager()->get(CustomerSession::class)->setCustomerId(null);
        parent::tearDown();
    }
}
