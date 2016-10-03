<?php
namespace Craft;

class m161001_010103_Commerce_DiscountStopProcessing extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_discounts','stopProcessing',ColumnType::Bool,'enabled');
        $data = [];
        $data['stopProcessing'] = 0;
        craft()->db->createCommand()->update('commerce_discounts', $data);
    }
}
