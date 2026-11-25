<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaUi\Model\CaptchaResponseResolverInterface;
use Magento\ReCaptchaUi\Model\ErrorMessageConfigInterface;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Yireo\HyvaWithoutGraphQl\Exception\ReviewInputException;

/**
 * Validates a reCAPTCHA token, but throws an exception instead of redirecting, so that the outcome
 * can be reported back as JSON.
 */
class ReCaptchaValidator
{
    public function __construct(
        private readonly IsCaptchaEnabledInterface $isCaptchaEnabled,
        private readonly CaptchaResponseResolverInterface $captchaResponseResolver,
        private readonly ValidationConfigResolverInterface $validationConfigResolver,
        private readonly ValidatorInterface $captchaValidator,
        private readonly ErrorMessageConfigInterface $errorMessageConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param string $key
     * @return void
     * @throws ReviewInputException
     */
    public function validate(RequestInterface $request, string $key): void
    {
        if (!$this->isCaptchaEnabled->isCaptchaEnabledFor($key)) {
            return;
        }

        $validationConfig = $this->validationConfigResolver->get($key);

        try {
            $response = $this->captchaResponseResolver->resolve($request);
        } catch (InputException) {
            throw new ReviewInputException(__($this->errorMessageConfig->getValidationFailureMessage()));
        }

        $validationResult = $this->captchaValidator->isValid($response, $validationConfig);
        if ($validationResult->isValid()) {
            return;
        }

        foreach ($validationResult->getErrors() as $errorCode => $errorMessage) {
            $this->logger->error('reCAPTCHA form validation error', [
                'form' => $key,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);
        }

        throw new ReviewInputException(__($this->errorMessageConfig->getValidationFailureMessage()));
    }
}
