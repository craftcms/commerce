<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types;

use Craft;
use craft\commerce\models\Store as StoreModel;
use craft\gql\base\ObjectType;
use craft\gql\base\SingularTypeInterface;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Class Store
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Store extends ObjectType implements SingularTypeInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'Store';
    }

    /**
     * @inheritdoc
     */
    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new self([
            'name' => self::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the type for stores.',
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
                'description' => 'The ID of the store.',
            ],
            'uid' => [
                'name' => 'uid',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The UUID of the store.',
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The name of the store.',
            ],
            'handle' => [
                'name' => 'handle',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The handle of the store.',
            ],
            'primary' => [
                'name' => 'primary',
                'type' => Type::nonNull(Type::boolean()),
                'description' => 'Whether this is the primary store.',
            ],
            'currency' => [
                'name' => 'currency',
                'type' => Type::string(),
                'description' => 'The ISO code of the store’s currency.',
            ],
        ];

        return Craft::$app->getGql()->prepareFieldDefinitions($fields, self::getName());
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var StoreModel $source */
        return match ($resolveInfo->fieldName) {
            'name' => $source->getName(),
            'currency' => $source->getCurrency()?->getCode(),
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
