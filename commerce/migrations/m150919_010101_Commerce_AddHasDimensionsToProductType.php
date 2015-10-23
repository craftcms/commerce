<?php
namespace Craft;

class m150919_010101_Commerce_AddHasDimensionsToProductType extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_producttypes', 'hasDimensions', ColumnType::Bool, 'handle');
        craft()->db->createCommand()->update('commerce_producttypes', ['hasDimensions' => 1]);

        return true;
    }
}