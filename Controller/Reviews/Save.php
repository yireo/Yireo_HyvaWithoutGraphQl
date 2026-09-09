<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Controller\Reviews;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;
use Yireo\HyvaWithoutGraphQl\Config\Config;
use Yireo\HyvaWithoutGraphQl\Controller\JsonResultBuilder;
use Yireo\HyvaWithoutGraphQl\Service\ReCaptchaValidator;
use Yireo\HyvaWithoutGraphQl\Service\ReviewSaver;

/**
 * Replaces the GraphQL `createProductReview` mutation of the Hyvä review form.
 *
 * CSRF is validated within `execute()` instead of by `Magento\Framework\App\Request\CsrfValidator`,
 * because the default validator skips AJAX requests and answers with a redirect instead of JSON.
 */
class Save implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const RECAPTCHA_KEY = 'product_review';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly JsonResultBuilder $jsonResultBuilder,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ReCaptchaValidator $reCaptchaValidator,
        private readonly ReviewSaver $reviewSaver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        if (!$this->config->isEnabled()) {
            return $this->jsonResultBuilder->createError((string)__('Not available.'), 404);
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->jsonResultBuilder->createError(
                (string)__('Invalid Form Key. Please refresh the page.'),
                403
            );
        }

        try {
            $this->reCaptchaValidator->validate($this->request, self::RECAPTCHA_KEY);

            $this->reviewSaver->save(
                (string)$this->request->getParam('sku', ''),
                trim((string)$this->request->getParam('nickname', '')),
                trim((string)$this->request->getParam('summary', '')),
                trim((string)$this->request->getParam('text', '')),
                $this->getRatings()
            );
        } catch (LocalizedException $exception) {
            return $this->jsonResultBuilder->createError($exception->getMessage(), 400);
        } catch (Throwable $throwable) {
            $this->logger->error('Unable to save product review: ' . $throwable->getMessage());

            return $this->jsonResultBuilder->createError(
                (string)__('We can\'t post your review right now.'),
                500
            );
        }

        return $this->jsonResultBuilder->create(['success' => true, 'messages' => []]);
    }

    /**
     * @return array<int, int>
     */
    private function getRatings(): array
    {
        $ratings = $this->request->getParam('ratings', []);
        if (!is_array($ratings)) {
            return [];
        }

        $validated = [];
        foreach ($ratings as $ratingId => $optionId) {
            if (!is_numeric($ratingId) || !is_numeric($optionId)) {
                continue;
            }

            $validated[(int)$ratingId] = (int)$optionId;
        }

        return $validated;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Bypass the framework CSRF validator for this AJAX endpoint.
     *
     * The default validator skips AJAX requests and would respond with a redirect instead of JSON.
     * CSRF protection is enforced explicitly inside execute() through the form-key validator.
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
