<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Controller;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Creates JSON results that are never stored by an HTTP cache, because all of them depend on the
 * current store, currency, customer group or customer session.
 */
class JsonResultBuilder
{
    public function __construct(
        private readonly JsonFactory $jsonFactory
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param int $httpStatusCode
     * @return Json
     */
    public function create(array $data, int $httpStatusCode = 200): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode($httpStatusCode);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);
        $result->setData($data);

        return $result;
    }

    /**
     * @param string $message
     * @param int $httpStatusCode
     * @return Json
     */
    public function createError(string $message, int $httpStatusCode = 400): Json
    {
        return $this->create(['success' => false, 'messages' => [$message]], $httpStatusCode);
    }
}
