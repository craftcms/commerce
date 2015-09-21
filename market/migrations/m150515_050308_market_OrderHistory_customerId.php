<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150515_050308_market_OrderHistory_customerId extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        $table     = Market_OrderHistoryRecord::model()->getTableName();
        $fullTable = craft()->db->addTablePrefix($table);

        $customersTable     = Market_CustomerRecord::model()->getTableName();
        $fullCustomersTable = craft()->db->addTablePrefix($customersTable);

        craft()->db->createCommand("ALTER TABLE $fullTable ADD COLUMN `customerId` INT NOT NULL AFTER `userId`;")->execute();
        craft()->db->createCommand("
            UPDATE $fullTable oh, $fullCustomersTable c
            SET oh.customerId = c.id
            WHERE oh.userId = c.userId
        ")->execute();

        craft()->db->createCommand()->addForeignKey($table, 'customerId',
            $customersTable, 'id', 'CASCADE', 'CASCADE');

        craft()->db->createCommand()->dropForeignKey($table, 'userId');
        craft()->db->createCommand()->dropColumn($table, 'userId');

        return true;
    }
}