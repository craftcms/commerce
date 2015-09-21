<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150414_134245_market_OrderStatusRecord extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        // Create the craft_market_orderstatuses table
        craft()->db->createCommand()->createTable('market_orderstatuses', [
            'name'        => ['required' => true],
            'orderTypeId' => [
                'decimals' => 0,
                'required' => true,
                'unsigned' => false,
                'length'   => 10,
                'column'   => 'integer'
            ],
            'handle'      => [
                'maxLength' => 255,
                'column'    => 'varchar',
                'required'  => true
            ],
            'color'       => [
                'maxLength' => 255,
                'column'    => 'char',
                'length'    => 7,
                'required'  => true
            ],
            'default'     => [
                'default'  => 0,
                'required' => true,
                'column'   => 'tinyint',
                'unsigned' => true
            ],
        ], null, true);

        // Add foreign keys to craft_market_orderstatuses
        craft()->db->createCommand()->addForeignKey('market_orderstatuses',
            'orderTypeId', 'market_ordertypes', 'id', null, null);

        // Create the craft_market_orderstatus_emails table
        craft()->db->createCommand()->createTable('market_orderstatus_emails', [
            'orderStatusId' => ['column' => 'integer', 'required' => true],
            'emailId'       => ['column' => 'integer', 'required' => true],
        ], null, true);

        // Add foreign keys to craft_market_orderstatus_emails
        craft()->db->createCommand()->addForeignKey('market_orderstatus_emails',
            'orderStatusId', 'market_orderstatuses', 'id', 'CASCADE',
            'CASCADE');
        craft()->db->createCommand()->addForeignKey('market_orderstatus_emails',
            'emailId', 'market_emails', 'id', 'CASCADE', 'CASCADE');

        return true;
    }
}
