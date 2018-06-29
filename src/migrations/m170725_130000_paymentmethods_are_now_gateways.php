<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;

/**
 * m170721_150000_paymentmethod_type_changes
 */
class m170725_130000_paymentmethods_are_now_gateways extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        MigrationHelper::renameTable('{{%commerce_paymentmethods}}', '{{%commerce_gateways}}', $this);
        $this->addColumn('{{%commerce_gateways}}', 'handle', $this->string()->notNull());

        $rows = (new Query())
            ->select(['id', 'name', 'type'])
            ->from('{{%commerce_gateways}}')
            ->all();

        foreach ($rows as $row) {
            $handle = StringHelper::toCamelCase(StringHelper::toAscii($row['name']));
            $type = 'craft\\commerce\\gateways\\' . $row['type'];
            $this->update('{{%commerce_gateways}}', ['handle' => $handle, 'type' => $type], ['id' => $row['id']]);
        }

        MigrationHelper::renameColumn('{{%commerce_orders}}', 'paymentMethodId', 'gatewayId', $this);
        MigrationHelper::renameColumn('{{%commerce_transactions}}', 'paymentMethodId', 'gatewayId', $this);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170721_150000_paymentmethod_type_changes cannot be reverted.\n";

        return false;
    }
}
