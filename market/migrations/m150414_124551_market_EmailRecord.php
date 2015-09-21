<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150414_124551_market_EmailRecord extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        // Create the craft_market_emails table
        craft()->db->createCommand()->createTable('market_emails', [
            'name'         => ['required' => true],
            'subject'      => ['required' => true],
            'to'           => [
                'maxLength' => 255,
                'column'    => 'varchar',
                'required'  => true
            ],
            'bcc'          => ['maxLength' => 255, 'column' => 'varchar'],
            'type'         => [
                'values'   => ['plain_text', 'html'],
                'column'   => 'enum',
                'required' => true
            ],
            'enabled'      => [
                'maxLength' => 1,
                'default'   => false,
                'required'  => true,
                'column'    => 'tinyint',
                'unsigned'  => true
            ],
            'templatePath' => ['required' => true],
        ], null, true);

        return true;
    }
}
