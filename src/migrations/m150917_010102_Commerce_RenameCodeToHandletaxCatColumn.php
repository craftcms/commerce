<?php
namespace Craft;

class m150917_010102_Commerce_RenameCodeToHandletaxCatColumn extends BaseMigration
{
    public function safeUp()
    {
        $this->renameColumn('commerce_taxcategories', 'code', 'handle');
        return true;
    }
}