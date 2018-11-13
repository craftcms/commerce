<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m180818_161906_fix_discountPurchasableType migration.
 */
class m180818_161906_fix_discountPurchasableType extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Blow it all away and let's rebuild it.
        if ($this->db->columnExists('{{%commerce_discount_purchasables}}', 'purchasableType')) {
            $this->dropColumn('{{%commerce_discount_purchasables}}', 'purchasableType');
        }

        $this->addColumn('{{%commerce_discount_purchasables}}', 'purchasableType', $this->string());

        $discountPurchasables = (new Query())
            ->select(['id', 'discountId', 'purchasableId', 'purchasableType'])
            ->limit(null)
            ->from('{{%commerce_discount_purchasables}}')
            ->all();

        foreach ($discountPurchasables as $discountPurchasable) {
            $purchasableType = (new Query())
                ->select(['type'])
                ->distinct(true)
                ->from(['{{%elements}}'])
                ->where(['id' => $discountPurchasable['purchasableId']])
                ->scalar();

            if ($purchasableType) {
                $this->update('{{%commerce_discount_purchasables}}', ['purchasableType' => $purchasableType], ['id' => $discountPurchasable['id']]);
            } else {
                // If there is no element type, drop the discount to purchasable relationship
                $this->delete('{{%commerce_discount_purchasables}}', ['id' => $discountPurchasable['id']]);
            }
        }

        // Put the column back to not null.
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_discount_purchasables}} alter column [[purchasableType]] type varchar(255), alter column [[purchasableType]] set not null');
        } else {
            $this->alterColumn('{{%commerce_discount_purchasables}}', 'purchasableType', $this->string()->notNull());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180818_161906_fix_discountPurchasableType cannot be reverted.\n";
        return false;
    }
}
