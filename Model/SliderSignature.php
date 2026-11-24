<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Model;

use Magento\Framework\App\DeploymentConfig;

/**
 * Signs the slider parameters that are rendered server-side, so that the public slider endpoint only
 * runs product queries that the store itself configured.
 */
class SliderSignature
{
    public const SIGNATURE_PARAMETER = 'sig';

    public const PARAMETER_KEYS = [
        'skus',
        'category_ids',
        'price_from',
        'price_to',
        'page_size',
        'sort_attribute',
        'sort_direction',
        'type',
        'filters',
    ];

    private const KEY_CONTEXT = 'yireo_hyva_without_graphql_slider';

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return string
     */
    public function sign(array $params): string
    {
        $key = $this->getKey();
        if ($key === '') {
            return '';
        }

        return hash_hmac('sha256', $this->getCanonicalString($params), $key);
    }

    /**
     * @param array<string, mixed> $params
     * @param string $signature
     * @return bool
     */
    public function verify(array $params, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = $this->sign($params);
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string, mixed> $params
     * @return string
     */
    private function getCanonicalString(array $params): string
    {
        unset($params[self::SIGNATURE_PARAMETER]);
        $params = array_intersect_key($params, array_flip(self::PARAMETER_KEYS));

        return http_build_query($this->canonicalize($params));
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>
     */
    private function canonicalize(array $params): array
    {
        $canonical = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = $this->canonicalize($value);
                if ($value === []) {
                    continue;
                }
            } elseif (is_scalar($value)) {
                $value = (string)$value;
                if ($value === '') {
                    continue;
                }
            } else {
                continue;
            }

            $canonical[(string)$key] = $value;
        }

        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    private function getKey(): string
    {
        $cryptKey = $this->deploymentConfig->get('crypt/key');
        if (!is_string($cryptKey)) {
            return '';
        }

        $keys = preg_split('/\s+/', trim($cryptKey, " \f\n\r\t\v\x00"));
        $cryptKey = is_array($keys) ? (string)end($keys) : '';
        if ($cryptKey === '') {
            return '';
        }

        return hash_hmac('sha256', self::KEY_CONTEXT, $cryptKey);
    }
}
