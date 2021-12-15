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
            '{{%commerce_countries}}' => [
                'isStateRequired' => false,
            ],
            '{{%commerce_discounts}}' => [
                'excludeOnSale' => false,
                'hasFreeShippingForMatchingItems' => false,
                'hasFreeShippingForOrder' => false,
                'allPurchasables' => false,
                'allCategories' => false,
                'enabled' => true,
                'stopProcessing' => false,
            ],
            '{{%commerce_donations}}' => [
                'availableForPurchase' => false,
            ],
            '{{%commerce_emails}}' => [
                'enabled' => true,
            ],
            '{{%commerce_pdfs}}' => [
                'enabled' => true,
                'isDefault' => false,
            ],
            '{{%commerce_gateways}}' => [
                'isFrontendEnabled' => true,
                'isArchived' => false,
            ],
            '{{%commerce_lineitemstatuses}}' => [
                'default' => false,
            ],
            '{{%commerce_orderadjustments}}' => [
                'included' => false,
            ],
            '{{%commerce_orders}}' => [
                'isCompleted' => false,
                'registerUserOnOrderComplete' => false,
            ],
            '{{%commerce_orderstatuses}}' => [
                'default' => false,
            ],
            '{{%commerce_plans}}' => [
                'enabled' => false,
                'isArchived' => false,
            ],
            '{{%commerce_products}}' => [
                'promotable' => false,
                'availableForPurchase' => true,
                'freeShipping' => false,
            ],
            '{{%commerce_producttypes}}' => [
                'hasDimensions' => false,
                'hasVariants' => false,
                'hasVariantTitleField' => true,
                'hasProductTitleField' => true,
            ],
            '{{%commerce_producttypes_sites}}' => [
                'hasUrls' => false,
            ],
            '{{%commerce_sales}}' => [
                'allGroups' => false,
                'allPurchasables' => false,
                'allCategories' => false,
                'enabled' => true,
                'ignorePrevious' => false,
                'stopProcessing' => false,
            ],
            '{{%commerce_shippingcategories}}' => [
                'default' => false,
            ],
            '{{%commerce_shippingmethods}}' => [
                'enabled' => true,
                'isLite' => false,
            ],
            '{{%commerce_shippingrules}}' => [
                'enabled' => true,
                'isLite' => false,
            ],
            '{{%commerce_shippingzones}}' => [
                'isCountryBased' => true,
            ],
            '{{%commerce_subscriptions}}' => [
                'isCanceled' => false,
                'isExpired' => false,
            ],
            '{{%commerce_taxcategories}}' => [
                'default' => false,
            ],
            '{{%commerce_taxrates}}' => [
                'isEverywhere' => true,
                'include' => false,
                'isVat' => false,
                'removeIncluded' => false,
                'removeVatIncluded' => false,
                'isLite' => false,
            ],
            '{{%commerce_taxzones}}' => [
                'isCountryBased' => true,
                'default' => false,
            ],
            '{{%commerce_variants}}' => [
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
