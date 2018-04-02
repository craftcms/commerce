<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\eway\gateways\gateway as EwayGateway;
use craft\commerce\multisafepay\gateways\gateway as MultiSafepayGateway;
use craft\commerce\paypal\gateways\PayPalExpress;
use craft\commerce\paypal\gateways\PayPalRest;
use craft\commerce\sagepay\gateways\Direct;
use craft\commerce\sagepay\gateways\Server;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m180401_161901_first_last_name_optional migration.
 */
class m180401_161901_first_last_name_optional extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Now we can set the groupId column to NOT NULL
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_addresses}} alter column [[firstName]] DROP NOT NULL');
            $this->execute('alter table {{%commerce_addresses}} alter column [[lastName]] DROP NOT NULL');
        } else {
            $this->alterColumn('{{%commerce_addresses}}', 'firstName', $this->string()->null());
            $this->alterColumn('{{%commerce_addresses}}', 'lastName', $this->string()->null());
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180401_161901_first_last_name_optional cannot be reverted.\n";
        return false;
    }
}
