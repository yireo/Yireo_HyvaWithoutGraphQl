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

    public function testNumberOfAttributeFiltersIsCapped(): void
    {
        $filters = [];
        for ($i = 1; $i <= 8; $i++) {
            $filters[] = 'attr' . $i . ': { eq: "value" }';
        }

        $result = $this->parser->parse(implode(', ', $filters));

        $this->assertCount(ProductFilterParser::MAX_ATTRIBUTE_FILTERS, $result);
        $this->assertSame(['attr1', 'attr2', 'attr3', 'attr4', 'attr5'], array_keys($result));
    }

    public function testInListIsCapped(): void
    {
        $values = [];
        for ($i = 1; $i <= 150; $i++) {
            $values[] = '"v' . $i . '"';
        }

        $result = $this->parser->parse('color: { in: [' . implode(',', $values) . '] }');

        $this->assertCount(ProductFilterParser::MAX_LIST_SIZE, $result['color']['in']);
        $this->assertSame('v1', $result['color']['in'][0]);
    }

    public function testNinListIsCapped(): void
    {
        $values = [];
        for ($i = 1; $i <= 150; $i++) {
            $values[] = '"v' . $i . '"';
        }

        $result = $this->parser->parse('color: { nin: [' . implode(',', $values) . '] }');

        $this->assertCount(ProductFilterParser::MAX_LIST_SIZE, $result['color']['nin']);
    }

    public function testShortMatchValueIsDropped(): void
    {
        $this->assertSame([], $this->parser->parse('name: { match: "ab" }'));
    }

    public function testWildcardOnlyLikeValueIsDropped(): void
    {
        $this->assertSame([], $this->parser->parse('name: { like: "%_%_ %" }'));
        $this->assertSame([], $this->parser->parse('name: { like: "%a_b%" }'));
    }

    public function testLikeValueWithEnoughCharactersIsKept(): void
    {
        $this->assertSame(
            ['name' => ['like' => '%bag%']],
            $this->parser->parse('name: { like: "%bag%" }')
        );
    }

    public function testShortLikeDoesNotDropOtherConditions(): void
    {
        $this->assertSame(
            ['name' => ['eq' => 'Bag']],
            $this->parser->parse('name: { like: "%a%", eq: "Bag" }')
        );
    }
}
