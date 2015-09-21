<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150605_010105_market_addRemainingDimensionsToLineItem extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        craft()->db->createCommand()->addColumnBefore('market_lineitems',
            'width', [
                ColumnType::Decimal,
                'length'   => 14,
                'decimals' => 4,
                'unsigned' => true,
                'required' => true,
                'default'  => 0.000
            ], 'total');
        craft()->db->createCommand()->addColumnBefore('market_lineitems',
            'height', [
                ColumnType::Decimal,
                'length'   => 14,
                'decimals' => 4,
                'unsigned' => true,
                'required' => true,
                'default'  => 0.000
            ], 'total');
        craft()->db->createCommand()->addColumnBefore('market_lineitems',
            'length', [
                ColumnType::Decimal,
                'length'   => 14,
                'decimals' => 4,
                'unsigned' => true,
                'required' => true,
                'default'  => 0.000
            ], 'total');

        return true;
    }
}
