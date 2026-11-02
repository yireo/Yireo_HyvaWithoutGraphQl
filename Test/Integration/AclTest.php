<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Integration;

use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 */
class AclTest extends TestCase
{
    public function testConfigResourceIsNestedUnderStoresConfiguration(): void
    {
        $tree = Bootstrap::getObjectManager()->get(ProviderInterface::class)->getAclResources();

        $path = $this->findPath($tree, 'Yireo_HyvaWithoutGraphQl::config');
        $this->assertNotNull($path, 'ACL resource Yireo_HyvaWithoutGraphQl::config not found');
        $this->assertSame(
            [
                'Magento_Backend::admin',
                'Magento_Backend::stores',
                'Magento_Backend::stores_settings',
                'Magento_Config::config',
                'Yireo_HyvaWithoutGraphQl::config',
            ],
            array_column($path, 'id')
        );
        $this->assertSame('Hyvä Without GraphQL', (string)end($path)['title']);
    }

    public function testConfigParentResourceIsNotDuplicated(): void
    {
        $tree = Bootstrap::getObjectManager()->get(ProviderInterface::class)->getAclResources();

        $this->assertSame(1, $this->countResource($tree, 'Magento_Config::config'));
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     * @return array<int, array<string, mixed>>|null
     */
    private function findPath(array $resources, string $id): ?array
    {
        foreach ($resources as $resource) {
            if ($resource['id'] === $id) {
                return [$resource];
            }

            $childPath = $this->findPath($resource['children'] ?? [], $id);
            if ($childPath !== null) {
                return array_merge([$resource], $childPath);
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     */
    private function countResource(array $resources, string $id): int
    {
        $count = 0;
        foreach ($resources as $resource) {
            if ($resource['id'] === $id) {
                $count++;
            }

            $count += $this->countResource($resource['children'] ?? [], $id);
        }

        return $count;
    }
}
