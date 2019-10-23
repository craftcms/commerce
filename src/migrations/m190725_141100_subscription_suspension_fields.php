<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190725_141100_subscription_suspension_fields migration.
 */
class m190725_141100_subscription_suspension_fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_subscriptions}}', 'hasStarted', $this->boolean()->notNull()->defaultValue(true));
        $this->addColumn('{{%commerce_subscriptions}}', 'isSuspended', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%commerce_subscriptions}}', 'dateSuspended', $this->dateTime());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190725_141100_subscription_suspension_fields cannot be reverted.\n";
        return false;
    }
}
