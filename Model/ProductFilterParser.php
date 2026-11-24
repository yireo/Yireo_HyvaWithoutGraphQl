<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Model;

/**
 * Parses the raw GraphQL filter fragment that the Hyvä slider accepts as `product_filters`
 * argument, for instance `color: { in: ["yellow","orange"] }, sku: { eq: "24-MB01" }`.
 */
class ProductFilterParser
{
    public const MAX_ATTRIBUTE_FILTERS = 5;
    public const MAX_LIST_SIZE = 100;
    public const MIN_LIKE_CHARACTERS = 3;

    private const ATTRIBUTE_PATTERN = '/([a-zA-Z0-9_]+)\s*:\s*\{([^{}]*)\}/';
    private const CONDITION_PATTERN = '/([a-zA-Z_]+)\s*:\s*(\[[^\]]*\]|"[^"]*"|\'[^\']*\'|[^,}\s]+)/';
    private const VALUE_PATTERN = '/"([^"]*)"|\'([^\']*)\'|([^,\[\]\s]+)/';

    private const CONDITION_MAP = [
        'eq' => 'eq',
        'neq' => 'neq',
        'in' => 'in',
        'nin' => 'nin',
        'from' => 'from',
        'to' => 'to',
        'gt' => 'gt',
        'gteq' => 'gteq',
        'lt' => 'lt',
        'lteq' => 'lteq',
        'like' => 'like',
        'match' => 'like',
    ];

    /**
     * @param string $filters
     * @return array<string, array<string, string|string[]>>
     */
    public function parse(string $filters): array
    {
        if (trim($filters, " \f\n\r\t\v\x00") === '') {
            return [];
        }

        if (preg_match_all(self::ATTRIBUTE_PATTERN, $filters, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $parsed = [];
        foreach ($matches as $match) {
            $conditions = $this->parseConditions($match[2]);
            if ($conditions === []) {
                continue;
            }

            if (count($parsed) >= self::MAX_ATTRIBUTE_FILTERS && !isset($parsed[$match[1]])) {
                break;
            }

            $parsed[$match[1]] = $conditions;
        }

        return $parsed;
    }

    /**
     * @param string $body
     * @return array<string, string|string[]>
     */
    private function parseConditions(string $body): array
    {
        if (preg_match_all(self::CONDITION_PATTERN, $body, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $conditions = [];
        foreach ($matches as $match) {
            $operator = strtolower($match[1]);
            if (!isset(self::CONDITION_MAP[$operator])) {
                continue;
            }

            $value = $this->parseValue($match[2]);
            if ($value === null) {
                continue;
            }

            $condition = self::CONDITION_MAP[$operator];
            if ($condition === 'like') {
                if (!is_string($value) || !$this->hasEnoughLikeCharacters($value)) {
                    continue;
                }

                if ($operator === 'match') {
                    $value = '%' . $value . '%';
                }
            }

            if (is_array($value)) {
                $value = array_slice($value, 0, self::MAX_LIST_SIZE);
            }

            $conditions[$condition] = $value;
        }

        return $conditions;
    }

    private function hasEnoughLikeCharacters(string $value): bool
    {
        $characters = preg_replace('/[%_\s]+/u', '', $value);

        return is_string($characters) && mb_strlen($characters) >= self::MIN_LIKE_CHARACTERS;
    }

    /**
     * @param string $value
     * @return string|string[]|null
     */
    private function parseValue(string $value): string|array|null
    {
        $value = trim($value, " \f\n\r\t\v\x00");
        if ($value === '') {
            return null;
        }

        if (!str_starts_with($value, '[')) {
            return $this->unquote($value);
        }

        if (preg_match_all(self::VALUE_PATTERN, substr($value, 1, -1), $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        $values = [];
        foreach ($matches as $match) {
            $values[] = $this->unquote($match[0]);
        }

        return $values === [] ? null : $values;
    }

    private function unquote(string $value): string
    {
        $value = trim($value, " \f\n\r\t\v\x00");
        if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
