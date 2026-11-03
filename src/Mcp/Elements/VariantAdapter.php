<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp\Elements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use Mcp\Exception\ToolCallException;

class VariantAdapter extends CommerceElementAdapter
{
    public static function handle(): string
    {
        return 'variants';
    }

    public static function elementType(): string
    {
        return Variant::class;
    }

    protected function createAttributesSchema(): array
    {
        $schema = $this->updateAttributesSchema();
        $schema['properties']['productId'] = ['type' => 'integer', 'minimum' => 1];
        $schema['required'] = ['productId'];
        return $schema;
    }

    protected function newElement(array $context, CraftUser $actor): ElementInterface
    {
        $element = new Variant();
        if ($context !== []) {
            $this->populate($element, $context, [], $actor);
        }
        return $element;
    }

    protected function populate(ElementInterface $element, array $attributes, array $fields, CraftUser $actor): void
    {
        if (isset($attributes['productId']) && $element instanceof Variant) {
            $product = Product::find()->id($attributes['productId'])->status(null)->one();
            if (!$product) {
                throw new ToolCallException('Product not found.');
            }
            $element->setPrimaryOwner($product);
            $element->siteId = $product->siteId;
            unset($attributes['productId']);
        }
        parent::populate($element, $attributes, $fields, $actor);
    }

    protected function updateAttributesSchema(): array
    {
        $nullableNumber = ['type' => ['number', 'null'], 'minimum' => 0];
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'title' => ['type' => 'string'],
            'sku' => ['type' => ['string', 'null']],
            'enabled' => ['type' => 'boolean'],
            'basePrice' => $nullableNumber,
            'basePromotionalPrice' => $nullableNumber,
            'width' => $nullableNumber,
            'height' => $nullableNumber,
            'length' => $nullableNumber,
            'weight' => $nullableNumber,
            'taxCategoryId' => ['type' => 'integer', 'minimum' => 1],
            'shippingCategoryId' => ['type' => 'integer', 'minimum' => 1],
            'minQty' => ['type' => ['integer', 'null'], 'minimum' => 0],
            'maxQty' => ['type' => ['integer', 'null'], 'minimum' => 0],
            'freeShipping' => ['type' => 'boolean'],
            'promotable' => ['type' => 'boolean'],
            'availableForPurchase' => ['type' => 'boolean'],
            'inventoryTracked' => ['type' => 'boolean'],
            'allowOutOfStockPurchases' => ['type' => 'boolean'],
        ]];
    }

    protected function criteriaProperties(): array
    {
        return [
            'productId' => ['type' => ['integer', 'array'], 'items' => ['type' => 'integer']],
            'sku' => ['type' => ['string', 'array'], 'items' => ['type' => 'string']],
            'isDefault' => ['type' => 'boolean'],
            'inventoryTracked' => ['type' => 'boolean'],
        ];
    }

    protected function fieldSchemaContextSchema(): array
    {
        return ['type' => 'object', 'properties' => ['productId' => ['type' => 'integer', 'minimum' => 1]], 'required' => ['productId'], 'additionalProperties' => false];
    }
}
