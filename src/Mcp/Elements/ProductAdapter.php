<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp\Elements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;

class ProductAdapter extends CommerceElementAdapter
{
    public static function handle(): string
    {
        return 'products';
    }

    public static function elementType(): string
    {
        return Product::class;
    }

    protected function createAttributesSchema(): array
    {
        $schema = $this->updateAttributesSchema();
        $schema['properties']['defaultVariant'] = ['type' => 'object', 'properties' => [
            'sku' => ['type' => 'string', 'minLength' => 1],
            'title' => ['type' => 'string'],
            'basePrice' => ['type' => 'number', 'minimum' => 0],
        ], 'required' => ['sku', 'basePrice'], 'additionalProperties' => false];
        $schema['required'] = ['typeId', 'title', 'defaultVariant'];
        return $schema;
    }

    protected function updateAttributesSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'typeId' => ['type' => 'integer', 'minimum' => 1],
            'siteId' => ['type' => 'integer', 'minimum' => 1],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'postDate' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'expiryDate' => ['type' => ['string', 'null'], 'format' => 'date-time'],
        ]];
    }

    protected function populate(ElementInterface $element, array $attributes, array $fields, CraftUser $actor): void
    {
        $variant = $attributes['defaultVariant'] ?? null;
        unset($attributes['defaultVariant']);
        parent::populate($element, $attributes, $fields, $actor);
        if ($variant !== null && $element instanceof Product) {
            $defaultVariant = new Variant(['title' => $element->title, ...$variant, 'siteId' => $element->siteId, 'isDefault' => true]);
            $defaultVariant->setPrimaryOwner($element);
            $element->setVariants([$defaultVariant]);
        }
    }

    protected function criteriaProperties(): array
    {
        return [
            'typeId' => ['type' => ['integer', 'array'], 'items' => ['type' => 'integer']],
            'type' => ['type' => ['string', 'array'], 'items' => ['type' => 'string']],
            'hasVariant' => ['type' => 'object'],
        ];
    }

    protected function fieldSchemaContextSchema(): array
    {
        return ['type' => 'object', 'properties' => ['typeId' => ['type' => 'integer', 'minimum' => 1], 'siteId' => ['type' => 'integer', 'minimum' => 1]], 'required' => ['typeId'], 'additionalProperties' => false];
    }

    protected function notes(): array
    {
        return ['Product saves create a default variant. Use elements.list with type variants and criteria.productId to find it.', 'Prices are in the store currency. Inventory quantities must be changed with commerce.inventory.update, not element attributes.'];
    }
}
