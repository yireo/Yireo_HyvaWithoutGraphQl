<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Unit\Model;

use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\TestCase;
use Yireo\HyvaWithoutGraphQl\Model\SliderSignature;

class SliderSignatureTest extends TestCase
{
    private const PARAMS = [
        'skus' => '24-MB01,24-MB02',
        'category_ids' => '',
        'page_size' => '4',
        'sort_attribute' => 'position',
        'sort_direction' => 'ASC',
        'type' => '',
        'filters' => 'color: { in: ["yellow"] }',
    ];

    public function testValidSignatureIsVerified(): void
    {
        $signature = $this->createSignature('key');

        $this->assertTrue($signature->verify(self::PARAMS, $signature->sign(self::PARAMS)));
    }

    public function testSignatureIsHexSha256(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->createSignature('key')->sign(self::PARAMS));
    }

    public function testTamperedParameterFailsVerification(): void
    {
        $signature = $this->createSignature('key');
        $sig = $signature->sign(self::PARAMS);

        $tampered = self::PARAMS;
        $tampered['page_size'] = '100';

        $this->assertFalse($signature->verify($tampered, $sig));
    }

    public function testAddedParameterFailsVerification(): void
    {
        $signature = $this->createSignature('key');
        $sig = $signature->sign(self::PARAMS);

        $tampered = self::PARAMS;
        $tampered['price_from'] = '1';

        $this->assertFalse($signature->verify($tampered, $sig));
    }

    public function testInvalidSignatureFailsVerification(): void
    {
        $signature = $this->createSignature('key');

        $this->assertFalse($signature->verify(self::PARAMS, ''));
        $this->assertFalse($signature->verify(self::PARAMS, str_repeat('0', 64)));
    }

    public function testOtherKeyFailsVerification(): void
    {
        $sig = $this->createSignature('key')->sign(self::PARAMS);

        $this->assertFalse($this->createSignature('other')->verify(self::PARAMS, $sig));
    }

    public function testParameterOrderSigParameterAndEmptyValuesDoNotMatter(): void
    {
        $signature = $this->createSignature('key');
        $sig = $signature->sign(self::PARAMS);

        $received = array_reverse(array_filter(self::PARAMS, static fn ($value): bool => $value !== ''));
        $received['sig'] = $sig;

        $this->assertTrue($signature->verify($received, $sig));
    }

    public function testUnknownParametersAreIgnored(): void
    {
        $signature = $this->createSignature('key');
        $sig = $signature->sign(self::PARAMS);

        $this->assertTrue($signature->verify(self::PARAMS + ['utm_source' => 'newsletter'], $sig));
    }

    public function testNestedArraysAreSortedRecursively(): void
    {
        $signature = $this->createSignature('key');
        $sig = $signature->sign(['skus' => ['b' => '2', 'a' => '1']]);

        $this->assertTrue($signature->verify(['skus' => ['a' => '1', 'b' => '2']], $sig));
    }

    public function testLastOfMultipleCryptKeysIsUsed(): void
    {
        $sig = $this->createSignature("old\nnew")->sign(self::PARAMS);

        $this->assertTrue($this->createSignature('new')->verify(self::PARAMS, $sig));
        $this->assertFalse($this->createSignature('old')->verify(self::PARAMS, $sig));
    }

    public function testMissingCryptKeyNeverVerifies(): void
    {
        $signature = $this->createSignature('');

        $this->assertFalse($signature->verify(self::PARAMS, $signature->sign(self::PARAMS)));
    }

    private function createSignature(string $cryptKey): SliderSignature
    {
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->with('crypt/key')->willReturn($cryptKey);

        return new SliderSignature($deploymentConfig);
    }
}
