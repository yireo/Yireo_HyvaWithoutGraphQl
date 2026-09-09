<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Model;

/**
 * Normalizes and validates the parameters that the slider component sends to the slider controller.
 */
class SliderRequestNormalizer
{
    public const MAX_PAGE_SIZE = 100;
    public const DEFAULT_PAGE_SIZE = 8;

    public const LINK_TYPES = ['related', 'upsell', 'crosssell'];

    private const SORT_ATTRIBUTES = [
        'position',
        'name',
        'price',
        'created_at',
        'entity_id',
        'sku',
    ];

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function normalize(array $params): array
    {
        return [
            'skus' => $this->toStringList($params['skus'] ?? ''),
            'category_ids' => $this->toIntList($params['category_ids'] ?? ''),
            'price_from' => $this->toFloatOrNull($params['price_from'] ?? null),
            'price_to' => $this->toFloatOrNull($params['price_to'] ?? null),
            'page_size' => $this->toPageSize($params['page_size'] ?? null),
            'sort_attribute' => $this->toSortAttribute($params['sort_attribute'] ?? null),
            'sort_direction' => $this->toSortDirection($params['sort_direction'] ?? null),
            'type' => $this->toType($params['type'] ?? null),
            'filters' => is_string($params['filters'] ?? null) ? trim($params['filters']) : '',
        ];
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function toStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $values = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $item = trim((string)$item);
            if ($item !== '') {
                $values[] = $item;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    private function toIntList(mixed $value): array
    {
        $values = [];
        foreach ($this->toStringList($value) as $item) {
            if (ctype_digit($item)) {
                $values[] = (int)$item;
            }
        }

        return $values;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function toPageSize(mixed $value): int
    {
        if (!is_scalar($value) || !is_numeric((string)$value)) {
            return self::DEFAULT_PAGE_SIZE;
        }

        $pageSize = (int)$value;
        if ($pageSize < 1) {
            return self::DEFAULT_PAGE_SIZE;
        }

        return min($pageSize, self::MAX_PAGE_SIZE);
    }

    private function toSortAttribute(mixed $value): string
    {
        if (is_string($value) && in_array($value, self::SORT_ATTRIBUTES, true)) {
            return $value;
        }

        return 'position';
    }

    private function toSortDirection(mixed $value): string
    {
        if (is_string($value) && strtoupper($value) === 'DESC') {
            return 'DESC';
        }

        return 'ASC';
    }

    private function toType(mixed $value): string
    {
        if (is_string($value) && in_array($value, self::LINK_TYPES, true)) {
            return $value;
        }

        return '';
    }
}
