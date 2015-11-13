<?php
namespace Craft;

class m151027_010102_Commerce_ProductDateNames extends BaseMigration
{
    public function safeUp()
    {
        $this->renameColumn('commerce_products','availableOn','postDate');
        $this->renameColumn('commerce_products','expiresOn','expiryDate');
    }
}
