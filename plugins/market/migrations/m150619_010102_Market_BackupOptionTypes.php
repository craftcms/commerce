<?php
namespace Craft;

class m150619_010102_Market_BackupOptionTypes extends BaseMigration
{
    public function safeUp()
    {

        $all = <<<EOT
select
vv.id as idx,
vv.variantId as variantId,
v.productId as variantProductId,
p.typeId as productTypeId,
ot.handle as optionTypeName,
ov.id as optionValueId,
ov.name as optionValueName,
ov.displayName as optionValueDisplayName
from craft_market_variant_optionvalues vv
	left join craft_market_variants v
		on vv.variantId = v.id
	left join craft_market_products p
		on v.productId = p.id
	left join craft_market_optionvalues ov
		on vv.optionValueId = ov.id
	left join craft_market_optiontypes ot
		on ov.optionTypeId = ot.id
EOT;

        $allData = craft()->db->createCommand($all)->queryAll();

        if (!empty($allData)) {
            craft()->db->createCommand()->createTable('market_variantoptionvaluesbackup',
                [
                    'idx'                    => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                    'variantId'              => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                    'variantProductId'       => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                    'productTypeId'          => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                    'optionTypeName'         => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                    'optionValueId'          => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                    'optionValueName'        => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                    'optionValueDisplayName' => [
                        'column'    => 'varchar',
                        'maxLength' => 255
                    ],
                ], null, false);

            foreach ($allData as $row) {
                $this->insert('market_variantoptionvaluesbackup', $row);
            }
        }

        $market_optionvalues = MigrationHelper::getTable('market_optionvalues');
        $market_optiontypes = MigrationHelper::getTable('market_optiontypes');
        $market_product_optiontypes = MigrationHelper::getTable('market_product_optiontypes');
        $market_variant_optionvalues = MigrationHelper::getTable('market_variant_optionvalues');

        MigrationHelper::dropAllForeignKeysOnTable($market_optionvalues);
        MigrationHelper::dropAllForeignKeysOnTable($market_optiontypes);
        MigrationHelper::dropAllForeignKeysOnTable($market_product_optiontypes);
        MigrationHelper::dropAllForeignKeysOnTable($market_variant_optionvalues);

        $this->dropTable('market_optionvalues');
        $this->dropTable('market_optiontypes');
        $this->dropTable('market_product_optiontypes');
        $this->dropTable('market_variant_optionvalues');

        return true;
    }
}