<?php
namespace Craft;

class m151110_010101_Commerce_RenameCompanyToAddress extends BaseMigration
{
    public function safeUp()
    {
        $this->renameColumn('commerce_addresses','companyNumber','businessTaxId');
        $this->renameColumn('commerce_addresses','company','businessName');
    }
}
