<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210806_082458_standard_address_format migration.
 */
class m210806_082458_standard_address_format extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_addresses}}', 'locality', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'dependentLocality', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'postalCode', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'sortingCode', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'addressLine1', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'addressLine2', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'organization', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'givenName', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'familyName', $this->string());
        $this->addColumn('{{%commerce_addresses}}', 'additionalName', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210806_082458_standard_address_format cannot be reverted.\n";
        return false;
    }
}
