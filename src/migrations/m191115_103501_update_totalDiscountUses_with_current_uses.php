<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use yii\db\Expression;

/**
 * m191115_103501_update_totalDiscountUses_with_current_uses migration.
 */
class m191115_103501_update_totalDiscountUses_with_current_uses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%commerce_discounts}}', ['totalDiscountUses' => new Expression('[[totalUses]]')], ['>', 'totalUses', 0]);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191115_103501_update_totalDiscountUses_with_current_uses cannot be reverted.\n";
        return false;
    }
}
