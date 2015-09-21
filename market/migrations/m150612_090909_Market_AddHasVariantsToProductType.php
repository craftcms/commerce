<?php
namespace Craft;

class m150612_090909_Market_AddHasVariantsToProductType extends BaseMigration
{
    public function safeUp()
    {
        // Allow transforms to have a format
        $this->addColumnAfter('market_producttypes', 'hasVariants',
            [ColumnType::Bool, 'required' => false], 'hasUrls');

        return true;
    }
}