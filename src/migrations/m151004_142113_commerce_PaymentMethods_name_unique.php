<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m151004_142113_commerce_PaymentMethods_name_unique extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        craft()->db->createCommand()->dropIndex('commerce_paymentmethods', 'class', true);
        craft()->db->createCommand()->createIndex('commerce_paymentmethods', 'name', true);
        return true;
    }
}
