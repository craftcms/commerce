<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180312_130001_countryBased migration.
 */
class m180312_130001_countryBased extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->columnExists('{{%commerce_shippingzones}}', 'countryBased')) {
            $this->renameColumn('{{%commerce_shippingzones}}', 'countryBased', 'isCountryBased');
        }

        if ($this->db->columnExists('{{%commerce_taxzones}}', 'countryBased')) {
            $this->renameColumn('{{%commerce_taxzones}}', 'countryBased', 'isCountryBased');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180312_130001_countryBased cannot be reverted.\n";
        return false;
    }
}
