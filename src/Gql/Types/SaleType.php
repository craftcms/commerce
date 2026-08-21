<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Types\DateTime;
use CraftCms\Cms\Gql\Types\ObjectType;
use CraftCms\Cms\Support\Facades\Gql;
use GraphQL\Type\Definition\Type;

class SaleType extends ObjectType
{
    public static function getName(): string
    {
        return 'Sale';
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new self([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => '',
        ]));
    }

    public static function getFieldDefinitions(): array
    {
        return Gql::prepareFieldDefinitions([
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
            'apply' => [
                'name' => 'apply',
                'type' => Type::string(),
                'description' => 'How the sale should be applied.',
            ],
            'applyAmount' => [
                'name' => 'applyAmount',
                'type' => Type::float(),
                'description' => 'The amount applied used by the apply option.',
            ],
            'applyAmountAsPercent' => [
                'name' => 'applyAmountAsPercent',
                'type' => Type::string(),
                'description' => 'The amount applied used by the apply option.',
            ],
            'applyAmountAsFlat' => [
                'name' => 'applyAmountAsFlat',
                'type' => Type::float(),
                'description' => 'The amount applied used by the apply option.',
            ],
            'dateFrom' => [
                'name' => 'dateFrom',
                'type' => DateTime::getType(),
                'description' => 'Start date of the sale.',
            ],
            'dateTo' => [
                'name' => 'dateTo',
                'type' => DateTime::getType(),
                'description' => 'Start date of the sale.',
            ],
        ], self::getName());
    }
}
