<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150507_100444_market_CustomerAddressRelationDelete extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        $table = craft()->db->addTablePrefix('market_addresses');
        craft()->db->createCommand("
            ALTER TABLE $table ADD COLUMN `customerId` INT NOT NULL AFTER `countryId`
        ")->execute();

        $relationTable = craft()->db->addTablePrefix('market_customer_addresses');
        craft()->db->createCommand("
            UPDATE $table a, $relationTable ca
            SET a.customerId = ca.customerId
            WHERE a.id = ca.addressId
        ")->execute();

        craft()->db->createCommand()->addForeignKey('market_addresses',
            'customerId', 'market_customers', 'id', 'CASCADE', 'CASCADE');

        craft()->db->createCommand()->dropTableIfExists('market_customer_addresses');

        return true;
    }
}
