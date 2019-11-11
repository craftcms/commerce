<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191017_183550_add_extra_address_fields migration.
 */
class m191017_183550_add_extra_address_fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_addresses}}', 'fullName', $this->string()->after('lastName'));
        $this->addColumn('{{%commerce_addresses}}', 'address3', $this->string()->after('address2'));
        $this->addColumn('{{%commerce_addresses}}', 'label', $this->string()->after('alternativePhone'));
        $this->addColumn('{{%commerce_addresses}}', 'notes', $this->text()->after('label'));
        $this->addColumn('{{%commerce_addresses}}', 'custom1', $this->string()->after('stateName'));
        $this->addColumn('{{%commerce_addresses}}', 'custom2', $this->string()->after('custom1'));
        $this->addColumn('{{%commerce_addresses}}', 'custom3', $this->string()->after('custom2'));
        $this->addColumn('{{%commerce_addresses}}', 'custom4', $this->string()->after('custom3'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191017_183550_add_extra_address_fields cannot be reverted.\n";
        return false;
    }
}
