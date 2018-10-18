<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180308_130001_has_and_is migration.
 */
class m180308_130001_has_and_is extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->columnExists('{{%commerce_addresses}}', 'storeLocation')) {
            $this->renameColumn('{{%commerce_addresses}}', 'storeLocation', 'isStoreLocation');
        }

        if ($this->db->columnExists('{{%commerce_countries}}', 'stateRequired')) {
            $this->renameColumn('{{%commerce_countries}}', 'stateRequired', 'isStateRequired');
        }

        if ($this->db->columnExists('{{%commerce_gateways}}', 'frontendEnabled')) {
            $this->renameColumn('{{%commerce_gateways}}', 'frontendEnabled', 'isFrontendEnabled');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180308_130001_has_and_is cannot be reverted.\n";
        return false;
    }
}
