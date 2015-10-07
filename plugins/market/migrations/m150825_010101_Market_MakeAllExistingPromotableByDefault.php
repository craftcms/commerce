<?php
namespace Craft;

class m150825_010101_Market_MakeAllExistingPromotableByDefault extends BaseMigration
{
    public function safeUp()
    {

        craft()->db->createCommand()->update('market_products',['promotable'=>1]);
        craft()->db->createCommand()->update('market_products',['freeShipping'=>0]);

        return true;
    }
}