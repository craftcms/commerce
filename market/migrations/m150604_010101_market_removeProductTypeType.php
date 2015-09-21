<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150604_010101_market_removeProductTypeType extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        craft()->db->createCommand()->dropColumn('market_producttypes',
            'type');
        craft()->db->createCommand()->addColumnBefore('market_producttypes',
            'hasUrls', [ColumnType::Bool], 'dateCreated');
        craft()->db->createCommand()->addColumnBefore('market_producttypes',
            'urlFormat', [ColumnType::Varchar], 'dateCreated');
        craft()->db->createCommand()->addColumnBefore('market_producttypes',
            'template', [ColumnType::Varchar], 'dateCreated');

        return true;
    }
}
