<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\queries;

use craft\base\Model;
use craft\commerce\gql\types\OrderStatus;
use craft\commerce\gql\types\ProductType;
use craft\commerce\gql\types\Store;
use craft\commerce\models\Store as StoreModel;
use craft\commerce\Plugin;
use craft\gql\base\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;

/**
 * Class Settings
 *
 * Defines read-only queries for product types, stores, and order statuses.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Settings extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        $queries = [];

        foreach (self::definitions() as $component => [$plural, $singular, $type, $description, $getAll, $extraArguments]) {
            if ($checkToken && !GqlHelper::canSchema("settings.$component")) {
                continue;
            }

            $arguments = array_merge(self::arguments(), $extraArguments);

            $queries[$plural] = [
                'type' => Type::nonNull(Type::listOf(Type::nonNull($type))),
                'args' => $arguments,
                'resolve' => fn($source, array $arguments) => self::filter($getAll(), $arguments),
                'description' => "This query is used to query for $description.",
            ];

            $queries[$singular] = [
                'type' => $type,
                'args' => $arguments,
                'resolve' => fn($source, array $arguments) => self::filter($getAll(), $arguments)[0] ?? null,
                'description' => "This query is used to query for a single $description.",
            ];
        }

        return $queries;
    }

    /**
     * Returns the query definitions, indexed by their schema component names.
     *
     * Each definition is an array containing the plural query name, the singular query name, the GraphQL type,
     * a description of what’s being queried, a callable that returns all of the models, and any additional
     * arguments the queries accept.
     *
     * @return array
     */
    private static function definitions(): array
    {
        $commerce = Plugin::getInstance();

        return [
            'producttypes' => ['productTypes', 'productType', ProductType::getType(), 'product types', fn() => $commerce->getProductTypes()->getAllProductTypes(), []],
            'stores' => ['stores', 'store', Store::getType(), 'stores', fn() => $commerce->getStores()->getAllStores()->all(), []],
            'orderstatuses' => ['orderStatuses', 'orderStatus', OrderStatus::getType(), 'order statuses', fn() => self::orderStatuses(), [
                'storeId' => [
                    'name' => 'storeId',
                    'type' => Type::listOf(Type::int()),
                    'description' => 'Narrows the query results based on the IDs of the stores they belong to.',
                ],
            ]],
        ];
    }

    /**
     * Returns the order statuses for all stores.
     *
     * @return \craft\commerce\models\OrderStatus[]
     */
    private static function orderStatuses(): array
    {
        $commerce = Plugin::getInstance();

        return $commerce->getStores()->getAllStores()
            ->flatMap(fn(StoreModel $store) => $commerce->getOrderStatuses()->getAllOrderStatuses($store->id)->all())
            ->all();
    }

    /**
     * Returns the arguments that all queries accept.
     *
     * @return array
     */
    private static function arguments(): array
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::listOf(Type::int()),
                'description' => 'Narrows the query results based on their IDs.',
            ],
            'uid' => [
                'name' => 'uid',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on their UUIDs.',
            ],
            'handle' => [
                'name' => 'handle',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on their handles.',
            ],
        ];
    }

    /**
     * Filters the given models by the query arguments.
     *
     * @param Model[] $models
     * @param array $arguments
     * @return Model[]
     */
    private static function filter(array $models, array $arguments): array
    {
        foreach ($arguments as $name => $values) {
            if ($values !== null) {
                $models = array_filter($models, fn(Model $model) => in_array(ArrayHelper::getValue($model, $name), $values, true));
            }
        }

        return array_values($models);
    }
}
