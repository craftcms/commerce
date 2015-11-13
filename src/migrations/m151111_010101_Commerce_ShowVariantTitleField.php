<?php
namespace Craft;

class m151111_010101_Commerce_ShowVariantTitleField extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_producttypes','hasVariantTitleField',ColumnType::Bool,'hasVariants');

        craft()->db->createCommand()->update('commerce_producttypes',['hasVariantTitleField' => 0]);

    }
}
