<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types;

use Craft;
use craft\commerce\models\OrderStatus as OrderStatusModel;
use craft\gql\base\ObjectType;
use craft\gql\base\SingularTypeInterface;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Gql;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Class OrderStatus
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class OrderStatus extends ObjectType implements SingularTypeInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'OrderStatus';
    }

    /**
     * @inheritdoc
     */
    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new self([
            'name' => self::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the type for order statuses.',
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
                'type' => Type::nonNull(Type::int()),
                'description' => 'The ID of the order status.',
            ],
            'uid' => [
                'name' => 'uid',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The UUID of the order status.',
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The name of the order status.',
            ],
            'handle' => [
                'name' => 'handle',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The handle of the order status.',
            ],
            'color' => [
                'name' => 'color',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The color of the order status.',
            ],
            'description' => [
                'name' => 'description',
                'type' => Type::string(),
                'description' => 'The description of the order status.',
            ],
            'default' => [
                'name' => 'default',
                'type' => Type::nonNull(Type::boolean()),
                'description' => 'Whether this is the store’s default order status.',
            ],
            'storeId' => [
                'name' => 'storeId',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The ID of the store the order status belongs to.',
            ],
        ];

        if (Gql::canSchema('settings.stores')) {
            $fields['store'] = [
                'name' => 'store',
                'type' => Type::nonNull(Store::getType()),
                'description' => 'The store the order status belongs to.',
            ];
        }

        return Craft::$app->getGql()->prepareFieldDefinitions($fields, self::getName());
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var OrderStatusModel $source */
        return match ($resolveInfo->fieldName) {
            'store' => $source->getStore(),
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
