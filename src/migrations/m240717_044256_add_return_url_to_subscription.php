<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240717_044256_add_return_url_to_subscription migration.
 */
class m240717_044256_add_return_url_to_subscription extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // add returnUrl to subscriptions table
        $this->addColumn('{{%commerce_subscriptions}}', 'returnUrl', $this->text()->after('isExpired'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240717_044256_add_return_url_to_subscription cannot be reverted.\n";
        return false;
    }
}
