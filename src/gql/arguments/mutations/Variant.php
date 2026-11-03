<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\mutations;

use craft\gql\base\ElementMutationArguments;
use GraphQL\Type\Definition\Type;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Variant extends ElementMutationArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'sku' => [
                'name' => 'sku',
                'type' => Type::string(),
                'description' => 'The variant’s SKU.',
            ],
            'basePrice' => [
                'name' => 'basePrice',
                'type' => Type::float(),
                'description' => 'The variant’s base price for the store the variant is being saved in.',
            ],
            'basePromotionalPrice' => [
                'name' => 'basePromotionalPrice',
                'type' => Type::float(),
                'description' => 'The variant’s base promotional price for the store the variant is being saved in.',
            ],
            'isDefault' => [
                'name' => 'isDefault',
                'type' => Type::boolean(),
                'description' => 'Whether this should be the product’s default variant.',
            ],
            'availableForPurchase' => [
                'name' => 'availableForPurchase',
                'type' => Type::boolean(),
                'description' => 'Whether the variant is available for purchase.',
            ],
            'promotable' => [
                'name' => 'promotable',
                'type' => Type::boolean(),
                'description' => 'Whether promotions can apply to the variant.',
            ],
            'freeShipping' => [
                'name' => 'freeShipping',
                'type' => Type::boolean(),
                'description' => 'Whether the variant ships for free.',
            ],
            'minQty' => [
                'name' => 'minQty',
                'type' => Type::int(),
                'description' => 'The minimum quantity of the variant that can be purchased.',
            ],
            'maxQty' => [
                'name' => 'maxQty',
                'type' => Type::int(),
                'description' => 'The maximum quantity of the variant that can be purchased.',
            ],
            'inventoryTracked' => [
                'name' => 'inventoryTracked',
                'type' => Type::boolean(),
                'description' => 'Whether the variant’s inventory is tracked.',
            ],
            'allowOutOfStockPurchases' => [
                'name' => 'allowOutOfStockPurchases',
                'type' => Type::boolean(),
                'description' => 'Whether the variant can be purchased when it’s out of stock.',
            ],
            'taxCategoryId' => [
                'name' => 'taxCategoryId',
                'type' => Type::int(),
                'description' => 'The ID of the variant’s tax category.',
            ],
            'shippingCategoryId' => [
                'name' => 'shippingCategoryId',
                'type' => Type::int(),
                'description' => 'The ID of the variant’s shipping category.',
            ],
            'width' => [
                'name' => 'width',
                'type' => Type::float(),
                'description' => 'The variant’s width.',
            ],
            'height' => [
                'name' => 'height',
                'type' => Type::float(),
                'description' => 'The variant’s height.',
            ],
            'length' => [
                'name' => 'length',
                'type' => Type::float(),
                'description' => 'The variant’s length.',
            ],
            'weight' => [
                'name' => 'weight',
                'type' => Type::float(),
                'description' => 'The variant’s weight.',
            ],
        ]);
    }
}
