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
 * m200102_185704_update_totalDiscountUseLimit_with_current_totalUseLimit migration.
 */
class m200102_185704_update_totalDiscountUseLimit_with_current_totalUseLimit extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%commerce_discounts}}', ['[[totalDiscountUseLimit]]' => new Expression('[[totalUseLimit]]')], ['>', '[[totalUseLimit]]', 0]);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200102_185704_update_totalDiscountUseLimit_with_current_totalUseLimit cannot be reverted.\n";
        return false;
    }
}
