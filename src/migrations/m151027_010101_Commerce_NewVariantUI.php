<?php
namespace Craft;

class m151027_010101_Commerce_NewVariantUI extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnBefore('commerce_variants','sortOrder',ColumnType::Int,'width');
        $this->dropColumn('commerce_variants','isImplicit');
    }
}
