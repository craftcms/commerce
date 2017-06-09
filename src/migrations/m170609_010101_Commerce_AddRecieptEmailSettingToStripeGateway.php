<?php

namespace Craft;

class m170609_010101_Commerce_AddRecieptEmailSettingToStripeGateway extends BaseMigration
{
    public function safeUp()
    {
        $stripeGateways = craft()->db->createCommand()->select('*')->from('commerce_paymentmethods')->where(['class' => 'stripe'])->queryAll();

        foreach ($stripeGateways as $gateway) {
            $settings = json_decode($gateway['settings'], true);

            $settings['includeReceiptEmailInRequests'] = '1';

            craft()->db->createCommand()->update('commerce_paymentmethods', ['settings' => json_encode($settings)], ['id' => $gateway['id']]);
        }
    }
}
