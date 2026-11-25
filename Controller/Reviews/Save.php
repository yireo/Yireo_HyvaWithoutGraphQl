<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Controller\Reviews;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Psr\Log\LoggerInterface;
use Throwable;
use Yireo\HyvaWithoutGraphQl\Config\Config;
use Yireo\HyvaWithoutGraphQl\Controller\JsonResultBuilder;
use Yireo\HyvaWithoutGraphQl\Exception\ReviewInputException;
use Yireo\HyvaWithoutGraphQl\Service\ReCaptchaValidator;
use Yireo\HyvaWithoutGraphQl\Service\ReviewSaver;

/**
 * Replaces the GraphQL `createProductReview` mutation of the Hyvä review form.
 *
 * The form key is always validated, also for AJAX requests, which the default CSRF validator skips.
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

        try {
            $this->reCaptchaValidator->validate($this->request, self::RECAPTCHA_KEY);

            $this->reviewSaver->save(
                $this->getStringParam('sku'),
                trim($this->getStringParam('nickname'), " \f\n\r\t\v\x00"),
                trim($this->getStringParam('summary'), " \f\n\r\t\v\x00"),
                trim($this->getStringParam('text'), " \f\n\r\t\v\x00"),
                $this->getRatings()
            );
        } catch (ReviewInputException $exception) {
            return $this->jsonResultBuilder->createError($exception->getMessage(), 400);
        } catch (Throwable $throwable) {
            $this->logger->error('Unable to save product review', ['exception' => $throwable]);

            return $this->jsonResultBuilder->createError(
                (string)__('We can\'t post your review right now.'),
                500
            );
        }

        return $this->jsonResultBuilder->create(['success' => true, 'messages' => []]);
    }

    /**
     * @param string $name
     * @return string
     * @throws ReviewInputException
     */
    private function getStringParam(string $name): string
    {
        $value = $this->request->getParam($name, '');
        if (!is_string($value)) {
            throw new ReviewInputException(__('Please fill in all required fields.'));
        }

        return $value;
    }

    /**
     * @return array<int, int>
     * @throws ReviewInputException
     */
    private function getRatings(): array
    {
        $ratings = $this->request->getParam('ratings', []);
        if ($ratings === null || $ratings === '') {
            return [];
        }

        if (!is_array($ratings)) {
            throw new ReviewInputException(__('The selected rating is not valid.'));
        }

        $validated = [];
        foreach ($ratings as $ratingId => $optionId) {
            if (!$this->isPositiveInteger($ratingId) || !$this->isPositiveInteger($optionId)) {
                throw new ReviewInputException(__('The selected rating is not valid.'));
            }

            $validated[(int)$ratingId] = (int)$optionId;
        }

        return $validated;
    }

    private function isPositiveInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        return is_string($value) && ctype_digit($value) && (int)$value > 0;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return new InvalidRequestException(
            $this->jsonResultBuilder->createError((string)__('Invalid Form Key. Please refresh the page.'), 403)
        );
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        if (!is_string($request->getParam('form_key'))) {
            return false;
        }

        return $this->formKeyValidator->validate($request);
    }
}
