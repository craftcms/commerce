<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m180329_161901_gateway_send_cart_info migration.
 */
class m180329_161901_gateway_send_cart_info extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $gateways = (new Query())
            ->select(['id', 'settings', 'type', 'sendCartInfo'])
            ->from(['{{%commerce_gateways}}'])
            ->where([
                'type' => [
                    'craft\commerce\eway\gateways\Gateway',
                    'craft\commerce\gateways\Eway_RapidDirect',
                    'craft\commerce\gateways\MultiSafepay_Rest',
                    'craft\commerce\gateways\PayPal_Express',
                    'craft\commerce\gateways\SagePay_Direct',
                    'craft\commerce\gateways\SagePay_Server',
                    'craft\commerce\multisafepay\gateways\Gateway',
                    'craft\commerce\paypal\gateways\PayPalExpress',
                    'craft\commerce\paypal\gateways\PayPalRest',
                    'craft\commerce\sagepay\gateways\Direct',
                    'craft\commerce\sagepay\gateways\Server',
                ]
            ])
            ->all($this->db);

        foreach ($gateways as $gateway) {

            $settings = Json::decodeIfJson($gateway['settings']);

            if (!is_array($settings)) {
                echo 'Gateway ' . $gateway['id'] . ' (' . $gateway['type'] . ') settings were invalid JSON: ' . $gateway['settings'] . "\n";
                $settings = [];
            }

            $settings['sendCartInfo'] = $gateway['sendCartInfo'];

            $this->update('{{%commerce_gateways}}', ['settings' => Json::encode($settings)], ['id' => $gateway['id']]);
        }

        $this->dropColumn('{{%commerce_gateways}}', 'sendCartInfo');
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180329_161901_gateway_send_cart_info cannot be reverted.\n";
        return false;
    }
}
