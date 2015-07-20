<?php
namespace Craft;

class m150720_010101_Market_RemoveTestingStripeTestGateway extends BaseMigration
{
    public function safeUp()
    {
        $methods = craft()->db->createCommand()
            ->select('*')
            ->from('market_paymentmethods')
            ->where("class = 'Stripe'")
            ->queryAll();

        foreach($methods as $method){
            $settings = json_decode($method['settings'],true);
            if($settings['apiKey'] == 'sk_test_8Lvmi5qDkbHRLCsyexhvOGuj'){
                craft()->db->createCommand()
                    ->delete('market_paymentmethods','id=:id', [':id'=>$method['id']]);
            }
        }
        return true;
    }
}