<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\models\ProductType;
use craft\errors\GqlException;
use craft\commerce\gql\types\elements\Product as ProductGqlType;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;
use GraphQL\Type\Definition\ResolveInfo;

class ProductResolverTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        // Mock the GQL token for the volumes below
        $this->tester->mockMethods(
            Craft::$app,
            'gql',
            ['getActiveSchema' => $this->make(GqlSchema::class, [
                'scope' => [
                    'productTypes.type-1-uid:read',
                    'productTypes.type-2-uid:read',
                ]
            ])]
        );
    }

    protected function _after()
    {
    }

    /**
     * Test resolving fields on products.
     *
     * @dataProvider productFieldTestDataProvider
     *
     * @param string $gqlTypeClass The Gql type class
     * @param string $propertyName The property being tested
     * @param mixed $result True for exact match, false for non-existing or a callback for fetching the data
     * @throws \Exception
     */
    public function testProductFieldResolving(string $gqlTypeClass, string $propertyName, $result)
    {
        $typeHandle = StringHelper::UUID();

        $mockElement = $this->make(
            ProductElement::class, [
                'postDate' => new \DateTime(),
                '__get' => function ($property) {
                    return in_array($property, ['plainTextField', 'typeface'], false) ? 'ok' : $this->$property;
                },
                'getType' => function () use ($typeHandle) {
                    return $this->make(ProductType::class, ['handle' => $typeHandle]);
                }
            ]
        );

        $this->_runTest($mockElement, $gqlTypeClass, $propertyName, $result);
    }

    /**
     * Run the test on an element for a type class with the property name.
     *
     * @param string $gqlTypeClass The Gql type class
     * @param string $propertyName The propery being tested
     * @param mixed $result True for exact match, false for non-existing or a callback for fetching the data
     */
    public function _runTest($element, string $gqlTypeClass, string $propertyName, $result)
    {
        $resolveInfo = $this->make(ResolveInfo::class, ['fieldName' => $propertyName]);
        $resolve = function () use ($gqlTypeClass, $element, $resolveInfo) {
            return $this->make($gqlTypeClass)->resolveWithDirectives($element, [], null, $resolveInfo);
        };

        if (is_callable($result)) {
            $this->assertEquals($result($element), $resolve());
        } else if ($result === true) {
            $this->assertEquals($element->$propertyName, $resolve());
            $this->assertNotNull($element->$propertyName);
        } else {
            $this->tester->expectThrowable(GqlException::class, $resolve);
        }
    }

    /**
     * @return array
     */
    public function productFieldTestDataProvider(): array
    {
        return [
            [ProductGqlType::class, 'productTypeHandle', function ($source) { return $source->getType()->handle;}],
            [ProductGqlType::class, 'plainTextField', true],
            [ProductGqlType::class, 'notAField', false],
        ];
    }
}
