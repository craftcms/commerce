<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210908_092553_address_international_support_updates migration.
 */
class m210908_092553_address_international_support_updates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->renameColumn('{{%commerce_addresses}}', 'stateId', 'administrativeAreaId');
        $this->renameColumn('{{%commerce_addresses}}', 'stateName', 'administrativeAreaName');
        $this->renameColumn('{{%commerce_addresses}}', 'firstName', 'givenName');
        $this->renameColumn('{{%commerce_addresses}}', 'lastName', 'familyName');
        $this->renameColumn('{{%commerce_addresses}}', 'address1', 'addressLine1');
        $this->renameColumn('{{%commerce_addresses}}', 'address2', 'addressLine2');
        $this->renameColumn('{{%commerce_addresses}}', 'address3', 'addressLine3');
        $this->renameColumn('{{%commerce_addresses}}', 'city', 'locality');
        $this->renameColumn('{{%commerce_addresses}}', 'zipCode', 'postalCode');
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m210908_092553_address_international_support_updates cannot be reverted.\n";
        return false;
    }
}
