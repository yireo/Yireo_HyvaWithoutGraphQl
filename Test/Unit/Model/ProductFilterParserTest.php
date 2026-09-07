<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Yireo\HyvaWithoutGraphQl\Model\ProductFilterParser;

class ProductFilterParserTest extends TestCase
{
    private ProductFilterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ProductFilterParser();
    }

    public function testEmptyStringReturnsNoFilters(): void
    {
        $this->assertSame([], $this->parser->parse(''));
        $this->assertSame([], $this->parser->parse('   '));
    }

    public function testGarbageReturnsNoFilters(): void
    {
        $this->assertSame([], $this->parser->parse('this is not a filter'));
    }

    public function testEqCondition(): void
    {
        $this->assertSame(
            ['sku' => ['eq' => '24-MB01']],
            $this->parser->parse('sku: { eq: "24-MB01" }')
        );
    }

    public function testInCondition(): void
    {
        $this->assertSame(
            ['color' => ['in' => ['yellow', 'orange']]],
            $this->parser->parse('color: { in: ["yellow","orange"] },')
        );
    }

    public function testInConditionWithSpacesAndSingleQuotes(): void
    {
        $this->assertSame(
            ['color' => ['in' => ['yellow', 'orange']]],
            $this->parser->parse("color: { in: [ 'yellow' , 'orange' ] }")
        );
    }

    public function testFromToCondition(): void
    {
        $this->assertSame(
            ['price' => ['from' => '10', 'to' => '20']],
            $this->parser->parse('price: { from: "10", to: "20" }')
        );
    }

    public function testMatchConditionBecomesLike(): void
    {
        $this->assertSame(
            ['name' => ['like' => '%shirt%']],
            $this->parser->parse('name: { match: "shirt" }')
        );
    }

    public function testMultipleAttributes(): void
    {
        $this->assertSame(
            [
                'color' => ['in' => ['yellow']],
                'sku' => ['eq' => '24-MB01'],
            ],
            $this->parser->parse('color: { in: ["yellow"] }, sku: { eq: "24-MB01" }')
        );
    }

    public function testUnknownOperatorIsIgnored(): void
    {
        $this->assertSame([], $this->parser->parse('color: { unsupported: "yellow" }'));
    }

    public function testUnknownOperatorDoesNotDropSupportedOperator(): void
    {
        $this->assertSame(
            ['color' => ['eq' => 'yellow']],
            $this->parser->parse('color: { unsupported: "x", eq: "yellow" }')
        );
    }

    public function testUnquotedValue(): void
    {
        $this->assertSame(
            ['price' => ['from' => '10']],
            $this->parser->parse('price: { from: 10 }')
        );
    }
}
