<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\Type;

/**
 * Class SaleType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.10
 */
class SaleType extends ObjectType
{
    /**
     * @return string|null
     */
    public static function getName(): string
    {
        return 'Sale';
    }

    /**
     * @return Type
     */
    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new self([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => '',
        ]));

        return $type;
    }

    /**
     * @return array
     */
    public static function getFieldDefinitions(): array
    {
        return TypeManager::prepareFieldDefinitions([
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
                'description' => 'The name of the sale as described in the control panel.',
            ],
            'description' => [
                'name' => 'description',
                'type' => Type::string(),
                'description' => 'Description of the sale.',
            ],
        ], self::getName());
    }
}
