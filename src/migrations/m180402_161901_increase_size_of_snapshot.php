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
 * m180402_161901_increase_size_of_snapshot migration.
 */
class m180402_161901_increase_size_of_snapshot extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Make fields larger to store more data
        $this->alterColumn('{{%commerce_lineitems}}', 'snapshot', $this->longText()->notNull());
        $this->alterColumn('{{%commerce_orderadjustments}}', 'sourceSnapshot', $this->longText()->notNull());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180402_161901_increase_size_of_snapshot cannot be reverted.\n";
        return false;
    }
}
