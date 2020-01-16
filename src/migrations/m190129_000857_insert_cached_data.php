<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Json;

/**
 * m190129_000857_insert_cached_data migration.
 */
class m190129_000857_insert_cached_data extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $db = Craft::$app->getDb();

        if (Craft::$app->getCache()->exists('commerce_discount_purchasables_001')) {
            $newSalesPurchasables = Craft::$app->getCache()->get('commerce_discount_purchasables_001');
            $newSalesPurchasables = Json::decode($newSalesPurchasables);
            foreach ($newSalesPurchasables as $newDiscountPurchasable) {
                $db->createCommand()
                    ->insert('{{%commerce_discount_purchasables}}', $newDiscountPurchasable)
                    ->execute();
            }
            Craft::$app->getCache()->delete('commerce_discount_purchasables_001');
        }

        if (Craft::$app->getCache()->exists('commerce_sale_purchasables_001')) {
            $newSalesPurchasables = Craft::$app->getCache()->get('commerce_sale_purchasables_001');
            $newSalesPurchasables = Json::decode($newSalesPurchasables);
            foreach ($newSalesPurchasables as $newSalePurchasable) {
                $db->createCommand()
                    ->insert('{{%commerce_sale_purchasables}}', $newSalePurchasable)
                    ->execute();
            }
            Craft::$app->getCache()->delete('commerce_sale_purchasables_001');
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190129_000857_insert_cached_data cannot be reverted.\n";
        return false;
    }
}
