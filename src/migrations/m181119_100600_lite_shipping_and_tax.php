<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;

/**
 * m181119_100600_lite_shipping_and_tax migration.
 */
class m181119_100600_lite_shipping_and_tax extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $taxRatesTable = '{{%commerce_taxrates}}';
        $shippingRulesTable = '{{%commerce_shippingrules}}';
        $shippingMethodsTable = '{{%commerce_shippingmethods}}';

        // Allow null tax zone for tax rates
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_taxrates}} alter column [[taxZoneId]] DROP NOT NULL');
        } else {
            $this->alterColumn($taxRatesTable, 'taxZoneId', $this->integer());
        }

        if (!$this->db->columnExists($shippingMethodsTable, 'isLite')) {
            $this->addColumn($shippingMethodsTable, 'isLite', $this->boolean());
        }

        if (!$this->db->columnExists($shippingRulesTable, 'isLite')) {
            $this->addColumn($shippingRulesTable, 'isLite', $this->boolean());
        }

        if (!$this->db->columnExists($taxRatesTable, 'isLite')) {
            $this->addColumn($taxRatesTable, 'isLite', $this->boolean());

        }

        if (!$this->db->columnExists($taxRatesTable, 'isEverywhere')) {
            $this->addColumn($taxRatesTable, 'isEverywhere', $this->boolean());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181119_100600_lite_shipping_and_tax cannot be reverted.\n";
        return false;
    }
}
