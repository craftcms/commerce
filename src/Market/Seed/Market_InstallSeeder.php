<?php

namespace Market\Seed;
use Craft\Market_OrderTypeModel;

/**
 * Default Seeder
 */
class Market_InstallSeeder implements Market_SeederInterface {

    public function seed()
    {
        $this->seedDefaultOrderType();
    }

    private function seedDefaultOrderType()
    {
        $orderType = new Market_OrderTypeModel;
        $orderType->name = 'Normal Order';
        $orderType->handle = 'normalOrder';

        // Set the field layout
        $fieldLayout       = \Craft\craft()->fields->assembleLayout([], []);
        $fieldLayout->type = 'Market_Order';
        $orderType->setFieldLayout($fieldLayout);

        \Craft\craft()->market_orderType->save($orderType);
    }
}