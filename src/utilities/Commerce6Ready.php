<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\utilities;

use Craft;
use craft\base\Utility;
use craft\commerce\base\Gateway;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCategoryConditionRule;
use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\Manual;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\Category;
use craft\elements\conditions\RelatedToConditionRule;
use craft\helpers\Json;
use craft\helpers\UrlHelper;

/**
 * Lists what needs to be fixed or updated before upgrading to Commerce 6.
 *
 * @since 5.7.8
 */
class Commerce6Ready extends Utility
{
    /**
     * The minimum PHP version Commerce 6 requires.
     */
    public const MIN_PHP_VERSION = '8.5.0';

    /**
     * Conditions that are stored in the database, by table, as `[label, condition columns, name column]`.
     */
    private const CONDITION_COLUMNS = [
        Table::CATALOG_PRICING_RULES => ['Catalog pricing rule', ['productCondition', 'variantCondition', 'purchasableCondition', 'customerCondition'], 'name'],
        Table::DISCOUNTS => ['Discount', ['orderCondition', 'customerCondition', 'shippingAddressCondition', 'billingAddressCondition'], 'name'],
        Table::GATEWAYS => ['Gateway', ['orderCondition', 'shippingAddressCondition', 'billingAddressCondition'], 'name'],
        Table::SHIPPINGMETHODS => ['Shipping method', ['orderCondition', 'customerCondition'], 'name'],
        Table::SHIPPINGRULES => ['Shipping rule', ['orderCondition', 'customerCondition'], 'name'],
        Table::STORESETTINGS => ['Store market', ['marketAddressCondition'], null],
    ];

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Commerce 6 Ready');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'commerce-6-ready';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'clipboard-list-check';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('commerce/_components/utilities/commerce-6-ready', [
            'checks' => self::checks(),
        ]);
    }

    /**
     * Returns the upgrade checks.
     *
     * Each check has a `label`, a `ready` flag, `instructions` (Markdown), and a list of `items` that need
     * attention, each with a `label` and optional `url` and `info`.
     *
     * @return array<array{label: string, ready: bool, instructions: string, items: array<array{label: string, url?: string|null, info?: string|null}>}>
     */
    public static function checks(): array
    {
        return [
            self::phpCheck(),
            self::categoriesCheck(),
            self::subscriptionsCheck(),
            self::gatewaysCheck(),
        ];
    }

    private static function phpCheck(): array
    {
        $ready = version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');

        return [
            'label' => Craft::t('commerce', 'PHP {version} or later', ['version' => self::MIN_PHP_VERSION]),
            'ready' => $ready,
            'instructions' => Craft::t('commerce', 'Commerce 6 requires PHP {version} or later. This server is running PHP {current}.', [
                'version' => self::MIN_PHP_VERSION,
                'current' => PHP_VERSION,
            ]),
            'items' => [],
        ];
    }

    private static function categoriesCheck(): array
    {
        $items = [];

        foreach (Craft::$app->getCategories()->getAllGroups() as $group) {
            $items[] = [
                'label' => Craft::t('commerce', 'Category group "{name}"', ['name' => $group->name]),
                'url' => UrlHelper::cpUrl('settings/categories/' . $group->id),
                'info' => "php craft entrify/categories $group->handle",
            ];
        }

        $promotionTables = [
            [Table::DISCOUNT_CATEGORIES, 'discountId', Table::DISCOUNTS, 'Discount', ['p.storeId']],
            [Table::SALE_CATEGORIES, 'saleId', Table::SALES, 'Sale', []],
        ];

        foreach ($promotionTables as [$joinTable, $idColumn, $table, $label, $extraColumns]) {
            $rows = (new Query())
                ->select(['p.id', 'p.name', ...$extraColumns])
                ->distinct()
                ->from(['j' => $joinTable])
                ->innerJoin(['e' => CraftTable::ELEMENTS], '[[e.id]] = [[j.categoryId]]')
                ->innerJoin(['p' => $table], "[[p.id]] = [[j.$idColumn]]")
                ->where(['e.type' => Category::class])
                ->all();

            foreach ($rows as $row) {
                $items[] = [
                    'label' => Craft::t('commerce', '{type} "{name}"', ['type' => Craft::t('commerce', $label), 'name' => $row['name']]),
                    'url' => $label === 'Discount'
                        ? Plugin::getInstance()->getDiscounts()->getDiscountById($row['id'], $row['storeId'])?->getCpEditUrl()
                        : Plugin::getInstance()->getSales()->getSaleById($row['id'])?->getCpEditUrl(),
                ];
            }
        }

        foreach (self::CONDITION_COLUMNS as $table => [$label, $columns, $nameColumn]) {
            $rows = (new Query())
                ->select(array_filter(['id', $nameColumn, in_array($table, [Table::CATALOG_PRICING_RULES, Table::DISCOUNTS], true) ? 'storeId' : null, ...$columns]))
                ->from($table)
                ->where(['or', ...array_map(fn(string $column) => ['like', $column, 'Category'], $columns)])
                ->all();

            foreach ($rows as $row) {
                $usesCategories = false;
                foreach ($columns as $column) {
                    if (self::conditionUsesCategories($row[$column] ?? null)) {
                        $usesCategories = true;
                        break;
                    }
                }

                if ($usesCategories) {
                    $items[] = [
                        'label' => $nameColumn
                            ? Craft::t('commerce', '{type} "{name}" conditions', ['type' => Craft::t('commerce', $label), 'name' => $row[$nameColumn]])
                            : Craft::t('commerce', '{type} conditions', ['type' => Craft::t('commerce', $label)]),
                        'url' => self::conditionOwnerUrl($table, (int)$row['id'], isset($row['storeId']) ? (int)$row['storeId'] : null),
                    ];
                }
            }
        }

        return [
            'label' => Craft::t('commerce', 'Convert categories to entries'),
            'ready' => empty($items),
            'instructions' => Craft::t('commerce', 'Commerce 6 doesn\'t support categories. Convert each category group to a section with the `entrify/categories` command. Discounts, sales, and catalog pricing rules keep their selections, because categories keep their IDs when they\'re converted to entries. Conditions that relate to categories should be updated to relate to the converted entries.'),
            'items' => $items,
        ];
    }

    private static function subscriptionsCheck(): array
    {
        $planCount = (int)(new Query())->from(Table::PLANS)->count();
        $subscriptionCount = (int)(new Query())->from(Table::SUBSCRIPTIONS)->count();

        return [
            'label' => Craft::t('commerce', 'Stop using subscriptions'),
            'ready' => $planCount === 0 && $subscriptionCount === 0,
            'instructions' => Craft::t('commerce', 'Commerce 6 doesn\'t support subscriptions. Existing plans and subscriptions stay in the database, but they can\'t be used or managed after upgrading. This site has {plans, number} {plans, plural, =1{plan} other{plans}} and {subscriptions, number} {subscriptions, plural, =1{subscription} other{subscriptions}}.', [
                'plans' => $planCount,
                'subscriptions' => $subscriptionCount,
            ]),
            'items' => [],
        ];
    }

    private static function gatewaysCheck(): array
    {
        $items = [];

        /** @var Gateway $gateway */
        foreach (Plugin::getInstance()->getGateways()->getAllGateways() as $gateway) {
            if ($gateway instanceof Dummy || $gateway instanceof Manual) {
                continue;
            }

            $items[] = [
                'label' => $gateway instanceof MissingGateway
                    ? Craft::t('commerce', 'Gateway "{name}" (missing)', ['name' => $gateway->name])
                    : Craft::t('commerce', 'Gateway "{name}" ({type})', ['name' => $gateway->name, 'type' => $gateway::displayName()]),
                'url' => $gateway->getCpEditUrl(),
            ];
        }

        return [
            'label' => Craft::t('commerce', 'Update gateway plugins'),
            'ready' => empty($items),
            'instructions' => Craft::t('commerce', 'Gateway plugins built for Commerce 5 don\'t work with Commerce 6. Before upgrading, make sure the plugin that provides each of these gateways has a version that supports Commerce 6.'),
            'items' => $items,
        ];
    }

    private static function conditionUsesCategories(?string $config): bool
    {
        if (!$config) {
            return false;
        }

        $config = Json::decodeIfJson($config);
        if (!is_array($config)) {
            return false;
        }

        foreach ($config['conditionRules'] ?? [] as $rule) {
            $class = $rule['class'] ?? null;

            if ($class === RelatedToConditionRule::class && ($rule['elementType'] ?? null) === Category::class) {
                return true;
            }

            if (
                $class === CatalogPricingRulePurchasableCategoryConditionRule::class &&
                !empty($rule['elementIds']) &&
                Category::find()->id($rule['elementIds'])->status(null)->site('*')->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private static function conditionOwnerUrl(string $table, int $id, ?int $storeId): ?string
    {
        return match ($table) {
            Table::CATALOG_PRICING_RULES => Plugin::getInstance()->getCatalogPricingRules()->getCatalogPricingRuleById($id, $storeId)?->getCpEditUrl(),
            Table::DISCOUNTS => Plugin::getInstance()->getDiscounts()->getDiscountById($id, $storeId)?->getCpEditUrl(),
            Table::GATEWAYS => Plugin::getInstance()->getGateways()->getGatewayById($id)?->getCpEditUrl(),
            default => null,
        };
    }
}
