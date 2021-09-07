<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\services\Emails;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\Pdfs;
use craft\commerce\services\ProductTypes;
use craft\db\Migration;

/**
 * m210901_211323_not_null_booleans migration.
 */
class m210901_211323_not_null_booleans extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->updateColumns();
        $this->updateProjectConfig();
        return true;
    }

    private function updateColumns(): void
    {
        $columns = [
            Table::COUNTRIES => [
                'isStateRequired' => false,
            ],
            Table::DISCOUNTS => [
                'excludeOnSale' => false,
                'hasFreeShippingForMatchingItems' => false,
                'hasFreeShippingForOrder' => false,
                'allPurchasables' => false,
                'allCategories' => false,
                'enabled' => true,
                'stopProcessing' => false,
            ],
            Table::DONATIONS => [
                'availableForPurchase' => false,
            ],
            Table::EMAILS => [
                'enabled' => true,
            ],
            Table::PDFS => [
                'enabled' => true,
                'isDefault' => false,
            ],
            Table::GATEWAYS => [
                'isFrontendEnabled' => true,
                'isArchived' => false,
            ],
            Table::LINEITEMSTATUSES => [
                'default' => false,
            ],
            Table::ORDERADJUSTMENTS => [
                'included' => false,
            ],
            Table::ORDERS => [
                'isCompleted' => false,
                'registerUserOnOrderComplete' => false,
            ],
            Table::ORDERSTATUSES => [
                'default' => false,
            ],
            Table::PLANS => [
                'enabled' => false,
                'isArchived' => false,
            ],
            Table::PRODUCTS => [
                'promotable' => false,
                'availableForPurchase' => true,
                'freeShipping' => false,
            ],
            Table::PRODUCTTYPES => [
                'hasDimensions' => false,
                'hasVariants' => false,
                'hasVariantTitleField' => true,
                'hasProductTitleField' => true,
            ],
            Table::PRODUCTTYPES_SITES => [
                'hasUrls' => false,
            ],
            Table::SALES => [
                'allGroups' => false,
                'allPurchasables' => false,
                'allCategories' => false,
                'enabled' => true,
                'ignorePrevious' => false,
                'stopProcessing' => false,
            ],
            Table::SHIPPINGCATEGORIES => [
                'default' => false,
            ],
            Table::SHIPPINGMETHODS => [
                'enabled' => true,
                'isLite' => false,
            ],
            Table::SHIPPINGRULES => [
                'enabled' => true,
                'isLite' => false,
            ],
            Table::SHIPPINGZONES => [
                'isCountryBased' => true,
            ],
            Table::SUBSCRIPTIONS => [
                'isCanceled' => false,
                'isExpired' => false,
            ],
            Table::TAXCATEGORIES => [
                'default' => false,
            ],
            Table::TAXRATES => [
                'isEverywhere' => true,
                'include' => false,
                'isVat' => false,
                'removeIncluded' => false,
                'removeVatIncluded' => false,
                'isLite' => false,
            ],
            Table::TAXZONES => [
                'isCountryBased' => true,
                'default' => false,
            ],
            Table::VARIANTS => [
                'isDefault' => false,
                'hasUnlimitedStock' => false,
                'deletedWithProduct' => false,
            ],
        ];

        $isPgsql = $this->db->getIsPgsql();

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $defaultValue) {
                // Set any null values to false
                $this->update($table, [$column => false], [$column => null], [], false);

                // Add a NOT NULL constraint and default value
                if ($isPgsql) {
                    // Manually construct the SQL for Postgres
                    // (see https://github.com/yiisoft/yii2/issues/12077)
                    $this->execute("ALTER TABLE $table ALTER COLUMN \"$column\" SET NOT NULL, " .
                        "ALTER COLUMN \"$column\" SET DEFAULT " . ($defaultValue ? 'TRUE' : 'FALSE'));
                } else {
                    $this->alterColumn($table, $column, $this->boolean()->notNull()->defaultValue($defaultValue));
                }
            }
        }
    }

    private function updateProjectConfig(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '4.0.0', '>=')) {
            return;
        }

        $projectConfig->muteEvents = true;

        $keys = [
            Gateways::CONFIG_GATEWAY_KEY => [
                'isFrontendEnabled',
                'isArchived',
            ],
            ProductTypes::CONFIG_PRODUCTTYPES_KEY => [
                'hasDimensions',
                'hasVariants',
                'hasVariantTitleField',
                'hasProductTitleField',
            ],
            OrderStatuses::CONFIG_STATUSES_KEY => [
                'default',
            ],
            Emails::CONFIG_EMAILS_KEY => [
                'enabled',
            ],
            Pdfs::CONFIG_PDFS_KEY => [
                'enabled',
                'isDefault',
            ],
        ];

        foreach ($keys as $basePath => $itemKeys) {
            $items = $projectConfig->get($basePath) ?? [];
            foreach ($items as $uid => $item) {
                foreach ($itemKeys as $key) {
                    $item[$key] = (bool)($item[$key] ?? false);
                }
                $projectConfig->set("$basePath.$uid", $item);
            }
        }

        $projectConfig->muteEvents = false;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210901_211323_not_null_booleans cannot be reverted.\n";
        return false;
    }
}
