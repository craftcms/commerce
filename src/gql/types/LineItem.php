<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types;

use Craft;
use craft\commerce\models\LineItem as LineItemModel;
use craft\gql\base\ObjectType;
use craft\gql\base\SingularTypeInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\DateTime;
use craft\helpers\Json;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Class LineItem
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class LineItem extends ObjectType implements SingularTypeInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'LineItem';
    }

    /**
     * @inheritdoc
     */
    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new self([
            'name' => self::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the type for order line items.',
        ]));
    }

    /**
     * Returns the type’s field definitions.
     *
     * @return array
     */
    public static function getFieldDefinitions(): array
    {
        $fields = [
            'id' => [
                'name' => 'id',
                'type' => Type::int(),
                'description' => 'The ID of the line item.',
            ],
            'uid' => [
                'name' => 'uid',
                'type' => Type::string(),
                'description' => 'The UUID of the line item.',
            ],
            'type' => [
                'name' => 'type',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The type of line item (`purchasable` or `custom`).',
            ],
            'purchasableId' => [
                'name' => 'purchasableId',
                'type' => Type::int(),
                'description' => 'The ID of the purchasable the line item is for.',
            ],
            'description' => [
                'name' => 'description',
                'type' => Type::string(),
                'description' => 'The description of the line item.',
            ],
            'sku' => [
                'name' => 'sku',
                'type' => Type::string(),
                'description' => 'The SKU of the line item.',
            ],
            'qty' => [
                'name' => 'qty',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The quantity of the line item.',
            ],
            'note' => [
                'name' => 'note',
                'type' => Type::string(),
                'description' => 'The customer’s note for the line item.',
            ],
            'options' => [
                'name' => 'options',
                'type' => Type::string(),
                'description' => 'The line item’s options, JSON-encoded.',
            ],
            'price' => [
                'name' => 'price',
                'type' => Type::float(),
                'description' => 'The price of the line item.',
            ],
            'promotionalPrice' => [
                'name' => 'promotionalPrice',
                'type' => Type::float(),
                'description' => 'The promotional price of the line item.',
            ],
            'salePrice' => [
                'name' => 'salePrice',
                'type' => Type::float(),
                'description' => 'The sale price of the line item.',
            ],
            'subtotal' => [
                'name' => 'subtotal',
                'type' => Type::float(),
                'description' => 'The line item’s subtotal (sale price × quantity).',
            ],
            'discount' => [
                'name' => 'discount',
                'type' => Type::float(),
                'description' => 'The line item’s discount amount.',
            ],
            'tax' => [
                'name' => 'tax',
                'type' => Type::float(),
                'description' => 'The line item’s tax amount.',
            ],
            'taxIncluded' => [
                'name' => 'taxIncluded',
                'type' => Type::float(),
                'description' => 'The line item’s included tax amount.',
            ],
            'shippingCost' => [
                'name' => 'shippingCost',
                'type' => Type::float(),
                'description' => 'The line item’s shipping cost.',
            ],
            'total' => [
                'name' => 'total',
                'type' => Type::float(),
                'description' => 'The line item’s total.',
            ],
            'onSale' => [
                'name' => 'onSale',
                'type' => Type::boolean(),
                'description' => 'Whether the line item is on sale.',
            ],
            'dateCreated' => [
                'name' => 'dateCreated',
                'type' => DateTime::getType(),
                'description' => 'The date the line item was created.',
            ],
            'dateUpdated' => [
                'name' => 'dateUpdated',
                'type' => DateTime::getType(),
                'description' => 'The date the line item was last updated.',
            ],
        ];

        return Craft::$app->getGql()->prepareFieldDefinitions($fields, self::getName());
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var LineItemModel $source */
        return match ($resolveInfo->fieldName) {
            'type' => $source->type->value,
            'options' => Json::encode($source->getOptions()),
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
